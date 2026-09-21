<?php

namespace App\Services;

use App\Models\AgenteConversacion;
use App\Models\Cliente;
use App\Models\Lead;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Agente recepcionista de leads de Vialum.
 * Atiende consultas nuevas (WhatsApp o simulador), califica al interesado
 * (qué necesita, material, comuna, nombre) y guarda el lead + avisa al vendedor.
 * NO cotiza ni promete precios: solo califica y deriva.
 */
class AgenteLeadsService
{
    private string $model    = 'claude-sonnet-4-6';
    private int    $maxTokens = 1024;

    /**
     * Procesa un mensaje del contacto y devuelve la respuesta del agente.
     * @return array{texto:string, lead:?array, estado:string}
     */
    public function procesarMensaje(AgenteConversacion $conv, string $texto): array
    {
        $messages   = $conv->historial ?? [];
        $messages[] = ['role' => 'user', 'content' => $texto];

        $leadCapturado = null;
        $content       = [];
        $iteration     = 0;
        $maxIter       = 6;

        do {
            $iteration++;
            $response = $this->callClaude($messages);

            if (isset($response['error'])) {
                return ['texto' => 'Disculpa, tuve un problema para responder. ¿Puedes repetirlo?', 'lead' => null, 'estado' => $conv->estado];
            }

            $stopReason = $response['stop_reason'] ?? 'end_turn';
            $content    = $response['content'] ?? [];

            if ($stopReason === 'tool_use') {
                $messages[]  = ['role' => 'assistant', 'content' => $content];
                $toolResults = [];

                foreach ($content as $block) {
                    if (($block['type'] ?? '') === 'tool_use') {
                        $result = $this->executeTool($block['name'], $block['input'], $conv);
                        if ($block['name'] === 'guardar_lead' && !empty($result['lead'])) {
                            $leadCapturado = $result['lead'];
                        }
                        $toolResults[] = [
                            'type'        => 'tool_result',
                            'tool_use_id' => $block['id'],
                            'content'     => json_encode($result),
                        ];
                    }
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];
            }
        } while ($stopReason === 'tool_use' && $iteration < $maxIter);

        $text = '';
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        $messages[] = ['role' => 'assistant', 'content' => $content];

        $conv->historial = $messages;
        $conv->save();

        return ['texto' => trim($text), 'lead' => $leadCapturado, 'estado' => $conv->estado];
    }

    // ── Claude ────────────────────────────────────────────────────────────────

    private function callClaude(array $messages): array
    {
        $client = new Client(['timeout' => 60]);

        try {
            $response = $client->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key'         => config('services.anthropic.key'),
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'system'     => $this->systemPrompt(),
                    'tools'      => $this->tools(),
                    'messages'   => $this->normalizeMessages($messages),
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error('AgenteLeads Anthropic: ' . $e->getResponse()->getBody()->getContents());
            return ['error' => true];
        } catch (\Exception $e) {
            Log::error('AgenteLeads callClaude: ' . $e->getMessage());
            return ['error' => true];
        }
    }

    // PHP decodifica {} como [] — Anthropic exige que tool_use.input sea objeto.
    private function normalizeMessages(array $messages): array
    {
        return array_map(function ($msg) {
            if (($msg['role'] ?? '') === 'assistant' && is_array($msg['content'] ?? null)) {
                $msg['content'] = array_map(function ($block) {
                    if (($block['type'] ?? '') === 'tool_use' && is_array($block['input'] ?? null)) {
                        $block['input'] = $this->toObject($block['input']);
                    }
                    return $block;
                }, $msg['content']);
            }
            return $msg;
        }, $messages);
    }

    private function toObject(array $arr): mixed
    {
        if (empty($arr) || array_keys($arr) !== range(0, count($arr) - 1)) {
            $obj = new \stdClass();
            foreach ($arr as $k => $v) {
                $obj->$k = is_array($v) ? $this->toObject($v) : $v;
            }
            return $obj;
        }
        return array_map(fn ($v) => is_array($v) ? $this->toObject($v) : $v, $arr);
    }

    // ── Tools ───────────────────────────────────────────────────────────────

    private function executeTool(string $name, array $input, AgenteConversacion $conv): array
    {
        return match ($name) {
            'buscar_cliente' => $this->toolBuscarCliente($input),
            'guardar_lead'   => $this->toolGuardarLead($input, $conv),
            default          => ['error' => "Tool '{$name}' no encontrada"],
        };
    }

    private function toolBuscarCliente(array $input): array
    {
        $q = $input['q'] ?? '';
        if (!$q) return [];

        return Cliente::where('razon_social', 'like', "%{$q}%")
            ->orWhere('first_name', 'like', "%{$q}%")
            ->orWhere('last_name', 'like', "%{$q}%")
            ->orWhere('identification', 'like', "%{$q}%")
            ->orWhere('phone', 'like', "%{$q}%")
            ->limit(4)
            ->get(['id', 'razon_social', 'first_name', 'last_name', 'phone', 'comuna'])
            ->map(fn ($c) => [
                'id'       => $c->id,
                'nombre'   => $c->razon_social ?: trim("{$c->first_name} {$c->last_name}"),
                'telefono' => $c->phone,
                'comuna'   => $c->comuna,
            ])->toArray();
    }

    private function toolGuardarLead(array $input, AgenteConversacion $conv): array
    {
        DB::beginTransaction();
        try {
            $lead = Lead::updateOrCreate(
                ['conversacion_id' => $conv->id],
                [
                    'categoria'         => $input['categoria']         ?? 'venta',
                    'nombre'            => $input['nombre']            ?? $conv->nombre_contacto,
                    'telefono'          => $input['telefono']          ?? ($conv->canal === 'whatsapp' ? $conv->identificador : null),
                    'email'             => $input['email']             ?? null,
                    'comuna'            => $input['comuna']            ?? null,
                    'tipo_producto'     => $input['tipo_producto']     ?? null,
                    'material'          => $input['material']          ?? null,
                    'tipo_obra'         => $input['tipo_obra']         ?? null,
                    'detalle'           => $input['detalle']           ?? null,
                    'presupuesto_aprox' => $input['presupuesto_aprox'] ?? null,
                    'origen'            => $conv->canal,
                    'estado'            => 'nuevo',
                    'cliente_id'        => $input['cliente_id']        ?? $conv->cliente_id,
                ]
            );

            $conv->update([
                'estado'          => 'calificado',
                'lead_id'         => $lead->id,
                'nombre_contacto' => $lead->nombre ?: $conv->nombre_contacto,
            ]);

            // Avisar al equipo con un recordatorio (aparece en agenda/calendario)
            $resumen = collect([
                $lead->tipo_producto ? ucfirst($lead->tipo_producto) : null,
                $lead->material ? ucfirst($lead->material) : null,
                $lead->comuna,
            ])->filter()->implode(' · ');

            $esPostventa = $lead->categoria === 'postventa';
            DB::table('recordatorios')->insert([
                'titulo'      => ($esPostventa ? '🔧 Postventa: ' : '🟢 Nuevo lead: ') . ($lead->nombre ?: 'sin nombre'),
                'descripcion' => trim(($resumen ? $resumen . '. ' : '') .
                                 'Tel: ' . ($lead->telefono ?: 's/n') . '. ' .
                                 ($lead->detalle ? ($esPostventa ? 'Problema: ' : 'Pide: ') . $lead->detalle : '')),
                'fecha'       => now()->toDateString(),
                'tipo'        => $esPostventa ? 'llamada' : 'tarea',
                'estado'      => 'pendiente',
                'cliente_id'  => $lead->cliente_id,
                'origen'      => 'ia',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            DB::commit();

            return [
                'ok'   => true,
                'lead' => [
                    'id'            => $lead->id,
                    'categoria'     => $lead->categoria,
                    'nombre'        => $lead->nombre,
                    'telefono'      => $lead->telefono,
                    'comuna'        => $lead->comuna,
                    'tipo_producto' => $lead->tipo_producto,
                    'material'      => $lead->material,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AgenteLeads guardar_lead: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo guardar el lead'];
        }
    }

    // ── Prompt y definición de tools ─────────────────────────────────────────

    private function systemPrompt(): string
    {
        return <<<PROMPT
Eres la recepcionista virtual de Vialum, empresa chilena de Los Ángeles (Región del Biobío) que
fabrica e instala a medida:
- Ventanas y puertas de PVC y aluminio.
- Shower / mamparas de baño y ducha en vidrio templado de 8 mm.
- Divisiones de oficina y tabiquerías en aluminio.
- REPARACIONES y CAMBIO DE VIDRIOS (simple/monolítico o termopanel) de ventanas y puertas de PVC y aluminio.
- Otras soluciones en vidrio y aluminio (más información en www.vialum.cl).

Atiendes a personas que escriben por primera vez consultando, normalmente por WhatsApp.

LO PRIMERO: clasifica el contacto en uno de estos tres casos:
- VENTA nueva: quiere cotizar/comprar un producto nuevo (ventanas, puertas, shower, división).
- REPARACIÓN o CAMBIO DE VIDRIO: necesita reparar o cambiar el vidrio de una ventana/puerta (ej. vidrio
  quebrado o trizado, cambiar a termopanel, ajuste de una hoja). Vialum SÍ hace esto y se COTIZA.
- POSTVENTA (garantía): un producto que INSTALÓ VIALUM presenta una falla (filtración, no cierra,
  desajuste, etc.) y busca que lo revisen. Es servicio técnico, no una venta.

MUY IMPORTANTE: NUNCA digas que la reparación o el cambio de vidrios "no es nuestro rubro" ni derives a
una vidriera externa. Vialum SÍ repara y cambia vidrios de ventanas y puertas de PVC y aluminio.

FLUJO REPARACIÓN / CAMBIO DE VIDRIO (se cotiza, categoria="venta"):
- Pregunta qué producto es (ventana o puerta) y si el vidrio es SIMPLE (monolítico) o TERMOPANEL. Si no
  sabe, ayúdalo: termopanel = doble vidrio con cámara de aire (mejor aislación); simple = un solo vidrio.
- Pide MEDIDAS APROXIMADAS del vidrio o de la ventana (alto × ancho) y la cantidad.
- Pide comuna/ubicación y el nombre.
- Guarda con guardar_lead, categoria="venta", tipo_producto="reparacion", e incluye en "detalle" el tipo
  de vidrio (simple/termopanel), medidas, cantidad y descripción del problema.

FLUJO POSTVENTA (falla de algo que instaló Vialum, en garantía; categoria="postventa"):
- Muestra empatía y disposición a ayudar (sin prometer soluciones ni plazos concretos).
- Confirma que el producto lo instaló Vialum.
- Identifica al cliente: pide su nombre y/o teléfono e intenta ubicarlo con buscar_cliente.
- Pide una descripción clara del problema (qué falla, hace cuánto, en qué producto/ubicación).
- NO pidas color ni ofrezcas cotización.
- Llama a guardar_lead con categoria="postventa" y el problema en "detalle".
- Cierra diciendo que el equipo de servicio técnico lo contactará para revisar el caso.

TU OBJETIVO EN VENTA: dar una buena primera atención, CALIFICAR al interesado y capturar sus datos y
su requerimiento para que un vendedor le prepare una cotización y lo contacte.

CÓMO FUNCIONA LA COTIZACIÓN EN VIALUM (MUY IMPORTANTE):
- La cotización se hace con MEDIDAS APROXIMADAS que entrega el propio cliente. Por eso SIEMPRE debes
  pedirle medidas aproximadas (alto y ancho de cada ventana/puerta/vano, o del shower/división) y la cantidad.
- Vialum toma las medidas exactas SOLO DESPUÉS de que el cliente acepta la cotización y paga un abono.
  NUNCA ofrezcas ni prometas una visita de medición antes del abono. Si preguntan, explícalo con amabilidad.
- No des precios ni valores tú: el vendedor prepara la cotización sin costo con los datos que reúnas.

QUÉ NECESITAS AVERIGUAR (de a poco, en conversación natural, NO como interrogatorio):
- Nombre de la persona.
- Qué necesita: ¿ventanas, puertas, shower de baño/ducha, división de oficina/tabiquería u otro?
- Material: PVC o aluminio (el shower es en vidrio templado). Si no sabe, anótalo.
- COLOR del aluminio o PVC que desea (ej. blanco, negro, gris, madera/roble). Pregúntalo siempre.
- TIPO DE VIDRIO: simple (monolítico) o termopanel (doble vidrio, mejor aislación térmica/acústica).
- MEDIDAS APROXIMADAS y cantidad (ej. "3 ventanas de 1,20 × 1,00 m").
- Comuna o sector (zona de despacho: Los Ángeles y alrededores del Biobío).
- Si es casa nueva, remodelación o si es constructora/empresa.

TONO Y ESTILO (MUY IMPORTANTE):
- Español de CHILE, cordial, respetuoso y profesional. Trata al cliente de USTED.
- NO seas exagerado ni "patudo": nada de "oye", "amigo/a" ni confianza excesiva.
- Puedes usar su nombre de forma cordial (ej. "Perfecto, Max,") pero sin coloquialismos.
- PROHIBIDO el voseo y los modismos argentinos: nunca uses "preferís", "querés", "tenés", "vos", "che".
  Usa siempre "prefiere", "quiere", "tiene", "usted".
- Breve, como WhatsApp: 1-3 frases por mensaje. Una o dos preguntas por mensaje, no todas juntas.

REGLAS:
- Si la persona ya es cliente, puedes buscarla con buscar_cliente (por nombre o teléfono).
- Cuando tengas lo esencial (nombre + qué necesita + comuna, idealmente también medidas aprox y color),
  llama a guardar_lead con TODO lo reunido (incluye medidas aproximadas y colores dentro de "detalle").
  Puedes volver a llamar guardar_lead si consigues más datos después.
- Tras guardar el lead, indica que con esos datos un vendedor le preparará la cotización sin costo y lo
  contactará a la brevedad. Ofrece si desea agregar algo más.
- Si preguntan por productos o servicios que no manejas con certeza, oriéntalo y menciona www.vialum.cl.
- Nunca inventes precios, plazos ni datos. No pidas RUT ni datos de pago en esta etapa.
PROMPT;
    }

    private function tools(): array
    {
        return [
            [
                'name'         => 'buscar_cliente',
                'description'  => 'Busca si el interesado ya es cliente de Vialum, por nombre o teléfono.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => ['q' => ['type' => 'string', 'description' => 'Nombre o teléfono a buscar']],
                    'required'   => ['q'],
                ],
            ],
            [
                'name'         => 'guardar_lead',
                'description'  => 'Registra o actualiza el contacto y avisa al equipo. Para VENTA: llamar cuando tengas al menos nombre, qué necesita y comuna. Para POSTVENTA: llamar cuando tengas nombre y la descripción del problema.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'categoria'         => ['type' => 'string', 'enum' => ['venta', 'postventa'], 'description' => 'venta = consulta/cotización nueva; postventa = problema, garantía o reparación de algo ya instalado'],
                        'nombre'            => ['type' => 'string'],
                        'telefono'          => ['type' => 'string'],
                        'email'             => ['type' => 'string'],
                        'comuna'            => ['type' => 'string'],
                        'tipo_producto'     => ['type' => 'string', 'enum' => ['ventanas', 'puertas', 'shower', 'division_oficina', 'reparacion', 'otro']],
                        'material'          => ['type' => 'string', 'enum' => ['pvc', 'aluminio', 'vidrio_templado', 'no_sabe']],
                        'tipo_obra'         => ['type' => 'string', 'enum' => ['casa_nueva', 'remodelacion', 'constructora', 'otro']],
                        'detalle'           => ['type' => 'string', 'description' => 'Resumen: medidas aproximadas, cantidad, COLOR del perfil, espacios y notas relevantes'],
                        'presupuesto_aprox' => ['type' => 'string'],
                        'cliente_id'        => ['type' => 'integer', 'description' => 'Si se identificó como cliente existente'],
                    ],
                    'required' => ['nombre'],
                ],
            ],
        ];
    }
}
