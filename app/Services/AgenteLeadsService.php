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

            DB::table('recordatorios')->insert([
                'titulo'      => '🟢 Nuevo lead: ' . ($lead->nombre ?: 'sin nombre'),
                'descripcion' => trim(($resumen ? $resumen . '. ' : '') .
                                 'Tel: ' . ($lead->telefono ?: 's/n') . '. ' .
                                 ($lead->detalle ? 'Pide: ' . $lead->detalle : '')),
                'fecha'       => now()->toDateString(),
                'tipo'        => 'tarea',
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
Eres la recepcionista virtual de Vialum, empresa chilena de Los Ángeles que fabrica e instala
ventanas y puertas de PVC y aluminio a medida. Atiendes a personas que escriben por primera vez
consultando (por WhatsApp normalmente).

TU OBJETIVO: dar una buena primera atención, CALIFICAR al interesado y capturar sus datos para que
un vendedor lo contacte. NO cotizas ni das precios en firme (las ventanas son a medida y requieren
visita de toma de medidas). Si preguntan precio, explica con amabilidad que depende de las medidas
y que un vendedor le prepara una cotización sin costo.

QUÉ NECESITAS AVERIGUAR (de a poco, en conversación natural, NO como interrogatorio):
- Nombre de la persona.
- Qué necesita: ¿ventanas, puertas u otro? ¿PVC o aluminio? (si no sabe, está bien, anótalo)
- Cuántas / para qué espacio, o si quiere una visita de toma de medidas.
- Comuna o sector (para saber si estamos en zona de despacho: Los Ángeles y alrededores del Biobío).
- Si es casa nueva, remodelación o si es constructora/empresa.

REGLAS:
- Español de Chile, cálido, cercano y breve (esto es WhatsApp: 1-3 frases por mensaje, sin párrafos largos).
- UNA o dos preguntas por mensaje, no todas juntas.
- Si la persona ya es cliente, puedes buscarla con buscar_cliente (por nombre o teléfono).
- Cuando ya tengas lo esencial (al menos nombre + qué necesita + comuna), llama a guardar_lead para
  registrarlo y avisar al vendedor. Puedes volver a llamar guardar_lead si consigues más datos después.
- Tras guardar el lead, dile que un vendedor lo contactará a la brevedad y ofrece si quiere agregar algo más.
- Nunca inventes precios, plazos exactos ni datos. Si no sabes algo, dilo y deriva al vendedor.
- No pidas RUT ni datos de pago en esta etapa.
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
                'description'  => 'Registra o actualiza el lead con los datos calificados y avisa al vendedor. Llamar cuando tengas al menos nombre, qué necesita y comuna.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'nombre'            => ['type' => 'string'],
                        'telefono'          => ['type' => 'string'],
                        'email'             => ['type' => 'string'],
                        'comuna'            => ['type' => 'string'],
                        'tipo_producto'     => ['type' => 'string', 'enum' => ['ventanas', 'puertas', 'otro']],
                        'material'          => ['type' => 'string', 'enum' => ['pvc', 'aluminio', 'no_sabe']],
                        'tipo_obra'         => ['type' => 'string', 'enum' => ['casa_nueva', 'remodelacion', 'constructora', 'otro']],
                        'detalle'           => ['type' => 'string', 'description' => 'Resumen en texto de lo que pide (espacios, cantidad, notas)'],
                        'presupuesto_aprox' => ['type' => 'string'],
                        'cliente_id'        => ['type' => 'integer', 'description' => 'Si se identificó como cliente existente'],
                    ],
                    'required' => ['nombre'],
                ],
            ],
        ];
    }
}
