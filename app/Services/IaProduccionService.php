<?php

namespace App\Services;

use App\Models\AusenciaEmpleado;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\EtapaProduccion;
use App\Models\HorasExtra;
use App\Models\IaMensaje;
use App\Models\IncidenteProduccion;
use App\Models\VisitaCliente;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IaProduccionService
{
    private string $apiUrl  = 'https://api.anthropic.com/v1/messages';
    private string $model   = 'claude-sonnet-4-6';
    private int    $maxTokens = 2048;

    // ── Punto de entrada principal ────────────────────────────────────────────

    /**
     * Procesa un mensaje del usuario, llama a Claude con tools y retorna la respuesta.
     */
    public function chat(string $mensaje, string $origen = 'app'): array
    {
        IaMensaje::create([
            'rol'     => 'user',
            'contenido' => $mensaje,
            'origen'  => $origen,
        ]);

        $historial   = $this->buildHistorial();
        $contexto    = $this->buildContexto();
        $tools       = $this->definirTools();
        $systemPrompt = $this->buildSystemPrompt($contexto);

        $accionesEjecutadas = [];
        $respuestaFinal     = '';

        $messages = $historial;
        $messages[] = ['role' => 'user', 'content' => $mensaje];

        // Loop de tool use — Claude puede llamar múltiples tools antes de responder
        for ($i = 0; $i < 5; $i++) {
            $response = $this->llamarClaude($systemPrompt, $messages, $tools);

            if (!$response) {
                $respuestaFinal = 'Hubo un error al contactar la IA. Intenta de nuevo.';
                break;
            }

            $stopReason = $response['stop_reason'] ?? 'end_turn';
            $content    = $response['content'] ?? [];

            if ($stopReason === 'end_turn') {
                foreach ($content as $block) {
                    if ($block['type'] === 'text') {
                        $respuestaFinal = $block['text'];
                    }
                }
                break;
            }

            if ($stopReason === 'tool_use') {
                // PHP decodifica {} como [] — hay que convertirlo a objeto para que
                // Claude API lo acepte como object en el historial de mensajes
                $contentNormalizado = array_map(function ($block) {
                    if (($block['type'] ?? '') === 'tool_use' && is_array($block['input'] ?? null) && empty($block['input'])) {
                        $block['input'] = new \stdClass();
                    }
                    return $block;
                }, $content);

                $assistantMessage = ['role' => 'assistant', 'content' => $contentNormalizado];
                $messages[]       = $assistantMessage;

                $toolResults = [];

                foreach ($content as $block) {
                    if ($block['type'] !== 'tool_use') {
                        continue;
                    }

                    $toolName   = $block['name'];
                    $toolInput  = $block['input'] ?? [];
                    $toolUseId  = $block['id'];

                    $resultado = $this->ejecutarTool($toolName, $toolInput);
                    $accionesEjecutadas[] = ['tool' => $toolName, 'resultado' => $resultado];

                    $toolResults[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $toolUseId,
                        'content'     => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];
                continue;
            }

            break;
        }

        IaMensaje::create([
            'rol'                => 'assistant',
            'contenido'          => $respuestaFinal,
            'acciones_ejecutadas' => $accionesEjecutadas,
            'origen'             => $origen,
        ]);

        return [
            'respuesta'          => $respuestaFinal,
            'acciones_ejecutadas' => $accionesEjecutadas,
        ];
    }

    // ── Claude API ────────────────────────────────────────────────────────────

    private function llamarClaude(string $system, array $messages, array $tools): ?array
    {
        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(60)->post($this->apiUrl, [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'system'     => $system,
            'tools'      => $tools,
            'messages'   => $messages,
        ]);

        if ($response->failed()) {
            Log::error('IaProduccionService: error llamando Claude', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        return $response->json();
    }

    // ── System prompt ─────────────────────────────────────────────────────────

    private function buildSystemPrompt(string $contexto): string
    {
        $hoy = now()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');

        return <<<PROMPT
Eres el asistente de gestión de producción de Vialum, una empresa fabricante de ventanas de aluminio en Chile.

Hoy es {$hoy}.

Tu rol es:
- Registrar eventos de producción que el usuario te comunica en lenguaje natural (ausencias, horas extra, incidentes, visitas, avances de etapas).
- Responder preguntas sobre el estado de la producción, entregas pendientes e incidentes.
- Ayudar a planificar y estimar fechas de entrega basándote en la carga actual y el historial.
- Ser proactivo: si detectas un problema (entrega próxima sin avance, incidente abierto antiguo), mencionarlo.

Reglas:
- Cuando el usuario mencione un evento, usa las tools disponibles para registrarlo en la base de datos antes de responder.
- Si el usuario menciona un empleado por nombre o apodo, busca en el contexto cuál es su id.
- Si mencionan una obra o cliente, busca la cotización correspondiente.
- Responde siempre en español, de forma directa y concisa. Sin saludos largos.
- Si no entiendes algo, pregunta la mínima información necesaria.

CONTEXTO ACTUAL DEL SISTEMA:
{$contexto}
PROMPT;
    }

    // ── Contexto de producción ────────────────────────────────────────────────

    private function buildContexto(): string
    {
        $cotizaciones = Cotizacion::with(['cliente', 'etapas'])
            ->whereIn('estado_produccion', [
                'Lista para Corte', 'En Fabricación', 'Fabricadas OK',
            ])
            ->orWhereNotNull('fecha_entrega')
            ->orderBy('fecha_entrega')
            ->limit(20)
            ->get();

        $empleados = Empleado::where('activo', true)
            ->select('id', 'nombre', 'cargo', 'workera_code')
            ->get();

        $incidentesAbiertos = IncidenteProduccion::with(['cotizacion.cliente', 'empleadoResponsable'])
            ->whereIn('estado', ['abierto', 'en_resolucion'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $ctx  = "=== EMPLEADOS ACTIVOS ===\n";
        foreach ($empleados as $e) {
            $ctx .= "ID:{$e->id} | {$e->nombre} | {$e->cargo}\n";
        }

        $ctx .= "\n=== COTIZACIONES EN PRODUCCIÓN ===\n";
        foreach ($cotizaciones as $c) {
            $cliente     = $c->cliente?->nombre ?? 'Sin cliente';
            $entrega     = $c->fecha_entrega ? Carbon::parse($c->fecha_entrega)->format('d/m/Y') : 'Sin fecha';
            $estado      = $c->estado_produccion ?? 'Sin estado';
            $m2          = $this->calcularM2($c->id);
            $ctx .= "ID:{$c->id} | {$cliente} | {$estado} | Entrega: {$entrega} | {$m2} m²\n";

            foreach ($c->etapas ?? [] as $etapa) {
                $ctx .= "  - Etapa {$etapa->etapa}: {$etapa->estado}";
                if ($etapa->empleado_id) {
                    $ctx .= " (asignado emp:{$etapa->empleado_id})";
                }
                $ctx .= "\n";
            }
        }

        $ctx .= "\n=== INCIDENTES ABIERTOS ===\n";
        if ($incidentesAbiertos->isEmpty()) {
            $ctx .= "Ninguno.\n";
        }
        foreach ($incidentesAbiertos as $inc) {
            $cliente = $inc->cotizacion?->cliente?->nombre ?? 'General';
            $ctx .= "ID:{$inc->id} | {$inc->tipo} | {$inc->estado} | {$cliente}: {$inc->descripcion}\n";
        }

        return $ctx;
    }

    private function calcularM2(int $cotizacionId): string
    {
        $m2 = \DB::table('ventanas')
            ->where('cotizacion_id', $cotizacionId)
            ->selectRaw('SUM((ancho/1000.0) * (alto/1000.0) * cantidad) as total')
            ->value('total');

        return number_format((float) $m2, 2);
    }

    // ── Historial de mensajes ─────────────────────────────────────────────────

    private function buildHistorial(): array
    {
        $mensajes = IaMensaje::orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        return $mensajes->map(fn($m) => [
            'role'    => $m->rol,
            'content' => $m->contenido,
        ])->toArray();
    }

    // ── Definición de tools ───────────────────────────────────────────────────

    private function definirTools(): array
    {
        return [
            [
                'name'        => 'registrar_ausencia',
                'description' => 'Registra la ausencia de un empleado. Úsalo cuando el usuario mencione que alguien no vino, llegó tarde o pidió permiso.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'empleado_id' => ['type' => 'integer', 'description' => 'ID del empleado en el sistema'],
                        'fecha'       => ['type' => 'string', 'description' => 'Fecha de la ausencia en formato YYYY-MM-DD'],
                        'tipo'        => ['type' => 'string', 'enum' => ['dia_completo', 'media_manana', 'media_tarde', 'llegada_tarde']],
                        'motivo'      => ['type' => 'string', 'description' => 'Motivo de la ausencia (opcional)'],
                    ],
                    'required' => ['empleado_id', 'fecha', 'tipo'],
                ],
            ],
            [
                'name'        => 'registrar_horas_extra',
                'description' => 'Registra horas extra de un empleado, opcionalmente asociadas a una cotización.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'empleado_id'  => ['type' => 'integer', 'description' => 'ID del empleado'],
                        'fecha'        => ['type' => 'string', 'description' => 'Fecha en formato YYYY-MM-DD'],
                        'horas'        => ['type' => 'number', 'description' => 'Cantidad de horas extra'],
                        'cotizacion_id' => ['type' => 'integer', 'description' => 'ID de la cotización/obra asociada (opcional)'],
                        'descripcion'  => ['type' => 'string', 'description' => 'Descripción de la tarea realizada'],
                    ],
                    'required' => ['empleado_id', 'fecha', 'horas'],
                ],
            ],
            [
                'name'        => 'registrar_incidente',
                'description' => 'Registra un incidente o problema en producción o instalación.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'descripcion'            => ['type' => 'string', 'description' => 'Descripción del incidente'],
                        'tipo'                   => ['type' => 'string', 'enum' => ['rotura_vidrio', 'retraso', 'material_faltante', 'instalacion', 'otro']],
                        'cotizacion_id'          => ['type' => 'integer', 'description' => 'ID de la cotización afectada (opcional)'],
                        'accion_requerida'       => ['type' => 'string', 'description' => 'Qué se debe hacer para resolverlo'],
                        'empleado_responsable_id' => ['type' => 'integer', 'description' => 'ID del empleado responsable (opcional)'],
                        'fecha_limite_resolucion' => ['type' => 'string', 'description' => 'Fecha límite para resolver en YYYY-MM-DD (opcional)'],
                    ],
                    'required' => ['descripcion', 'tipo'],
                ],
            ],
            [
                'name'        => 'registrar_visita',
                'description' => 'Registra una visita programada o realizada (medición, instalación, postventa).',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'cotizacion_id' => ['type' => 'integer', 'description' => 'ID de la cotización (opcional)'],
                        'cliente_id'    => ['type' => 'integer', 'description' => 'ID del cliente (opcional)'],
                        'tipo'          => ['type' => 'string', 'enum' => ['medicion', 'instalacion', 'postventa', 'otro']],
                        'fecha'         => ['type' => 'string', 'description' => 'Fecha de la visita en YYYY-MM-DD'],
                        'hora'          => ['type' => 'string', 'description' => 'Hora en formato HH:MM (opcional)'],
                        'empleado_ids'  => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'IDs de empleados que van a la visita'],
                        'notas'         => ['type' => 'string', 'description' => 'Notas adicionales'],
                        'estado'        => ['type' => 'string', 'enum' => ['programada', 'realizada', 'cancelada'], 'description' => 'Estado de la visita'],
                    ],
                    'required' => ['tipo', 'fecha'],
                ],
            ],
            [
                'name'        => 'actualizar_etapa',
                'description' => 'Actualiza el estado de una etapa de producción de una cotización.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'cotizacion_id' => ['type' => 'integer', 'description' => 'ID de la cotización'],
                        'etapa'         => ['type' => 'string', 'enum' => ['corte_perfiles', 'corte_vidrio', 'fabricacion_termopanel', 'armado', 'vidriado', 'junquillos', 'control', 'instalacion', 'entrega']],
                        'estado'        => ['type' => 'string', 'enum' => ['pendiente', 'en_progreso', 'completado']],
                        'empleado_id'   => ['type' => 'integer', 'description' => 'ID del empleado que realiza esta etapa (opcional)'],
                        'notas'         => ['type' => 'string', 'description' => 'Notas sobre la etapa (opcional)'],
                    ],
                    'required' => ['cotizacion_id', 'etapa', 'estado'],
                ],
            ],
            [
                'name'        => 'get_contexto_produccion',
                'description' => 'Obtiene información actualizada sobre cotizaciones en producción, empleados e incidentes.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'cotizacion_id' => ['type' => 'integer', 'description' => 'Si quieres detalle de una cotización específica (opcional)'],
                    ],
                ],
            ],
            [
                'name'        => 'estimar_fecha_entrega',
                'description' => 'Estima la fecha de entrega realista para una cotización basándose en la carga actual y el historial.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'cotizacion_id' => ['type' => 'integer', 'description' => 'ID de la cotización a estimar'],
                    ],
                    'required' => ['cotizacion_id'],
                ],
            ],
        ];
    }

    // ── Ejecución de tools ────────────────────────────────────────────────────

    private function ejecutarTool(string $tool, array $input): array
    {
        return match ($tool) {
            'registrar_ausencia'      => $this->toolRegistrarAusencia($input),
            'registrar_horas_extra'   => $this->toolRegistrarHorasExtra($input),
            'registrar_incidente'     => $this->toolRegistrarIncidente($input),
            'registrar_visita'        => $this->toolRegistrarVisita($input),
            'actualizar_etapa'        => $this->toolActualizarEtapa($input),
            'get_contexto_produccion' => $this->toolGetContexto($input),
            'estimar_fecha_entrega'   => $this->toolEstimarFecha($input),
            default                   => ['error' => "Tool desconocida: {$tool}"],
        };
    }

    private function toolRegistrarAusencia(array $input): array
    {
        $ausencia = AusenciaEmpleado::updateOrCreate(
            [
                'empleado_id' => $input['empleado_id'],
                'fecha'       => $input['fecha'],
                'tipo'        => $input['tipo'],
            ],
            ['motivo' => $input['motivo'] ?? null]
        );

        $empleado = Empleado::find($input['empleado_id']);

        return [
            'ok'      => true,
            'id'      => $ausencia->id,
            'mensaje' => "Ausencia registrada: {$empleado?->nombre} — {$input['tipo']} el {$input['fecha']}",
        ];
    }

    private function toolRegistrarHorasExtra(array $input): array
    {
        $registro = HorasExtra::create([
            'empleado_id'  => $input['empleado_id'],
            'cotizacion_id' => $input['cotizacion_id'] ?? null,
            'fecha'        => $input['fecha'],
            'horas'        => $input['horas'],
            'descripcion'  => $input['descripcion'] ?? null,
        ]);

        $empleado = Empleado::find($input['empleado_id']);

        return [
            'ok'      => true,
            'id'      => $registro->id,
            'mensaje' => "Horas extra registradas: {$empleado?->nombre} — {$input['horas']}h el {$input['fecha']}",
        ];
    }

    private function toolRegistrarIncidente(array $input): array
    {
        $incidente = IncidenteProduccion::create([
            'cotizacion_id'           => $input['cotizacion_id'] ?? null,
            'descripcion'             => $input['descripcion'],
            'tipo'                    => $input['tipo'],
            'estado'                  => 'abierto',
            'accion_requerida'        => $input['accion_requerida'] ?? null,
            'empleado_responsable_id' => $input['empleado_responsable_id'] ?? null,
            'fecha_limite_resolucion' => $input['fecha_limite_resolucion'] ?? null,
        ]);

        return [
            'ok'      => true,
            'id'      => $incidente->id,
            'mensaje' => "Incidente registrado (ID:{$incidente->id}): {$input['tipo']}",
        ];
    }

    private function toolRegistrarVisita(array $input): array
    {
        $visita = VisitaCliente::create([
            'cotizacion_id' => $input['cotizacion_id'] ?? null,
            'cliente_id'    => $input['cliente_id'] ?? null,
            'tipo'          => $input['tipo'],
            'fecha'         => $input['fecha'],
            'hora'          => $input['hora'] ?? null,
            'estado'        => $input['estado'] ?? 'programada',
            'notas'         => $input['notas'] ?? null,
        ]);

        if (!empty($input['empleado_ids'])) {
            $visita->empleados()->sync($input['empleado_ids']);
        }

        return [
            'ok'      => true,
            'id'      => $visita->id,
            'mensaje' => "Visita registrada (ID:{$visita->id}): {$input['tipo']} el {$input['fecha']}",
        ];
    }

    private function toolActualizarEtapa(array $input): array
    {
        $datos = [
            'estado'      => $input['estado'],
            'empleado_id' => $input['empleado_id'] ?? null,
            'notas'       => $input['notas'] ?? null,
        ];

        if ($input['estado'] === 'en_progreso') {
            $datos['fecha_inicio'] = now()->toDateString();
        } elseif ($input['estado'] === 'completado') {
            $datos['fecha_fin_real'] = now()->toDateString();
        }

        $etapa = EtapaProduccion::updateOrCreate(
            ['cotizacion_id' => $input['cotizacion_id'], 'etapa' => $input['etapa']],
            $datos
        );

        return [
            'ok'      => true,
            'id'      => $etapa->id,
            'mensaje' => "Etapa {$input['etapa']} de cotización {$input['cotizacion_id']} → {$input['estado']}",
        ];
    }

    private function toolGetContexto(array $input): array
    {
        if (!empty($input['cotizacion_id'])) {
            $c = Cotizacion::with(['cliente', 'ventanas', 'etapas.empleado'])->find($input['cotizacion_id']);

            if (!$c) {
                return ['error' => 'Cotización no encontrada'];
            }

            return [
                'cotizacion_id'   => $c->id,
                'cliente'         => $c->cliente?->nombre,
                'estado_produccion' => $c->estado_produccion,
                'fecha_entrega'   => $c->fecha_entrega,
                'm2_total'        => $this->calcularM2($c->id),
                'etapas'          => $c->etapas->map(fn($e) => [
                    'etapa'   => $e->etapa,
                    'estado'  => $e->estado,
                    'empleado' => $e->empleado?->nombre,
                ])->toArray(),
            ];
        }

        return ['contexto' => $this->buildContexto()];
    }

    private function toolEstimarFecha(array $input): array
    {
        $cotizacionId = $input['cotizacion_id'];
        $m2Cotizacion = (float) $this->calcularM2($cotizacionId);

        // Velocidad histórica: m² completados por día hábil por etapa
        $velocidades = $this->calcularVelocidades();

        // Backlog actual por etapa (m² en otras cotizaciones sin completar)
        $etapas = ['corte_perfiles', 'armado', 'vidriado', 'junquillos', 'control'];
        $diasTotales = 0;
        $detalle = [];

        foreach ($etapas as $etapa) {
            $backlogM2 = \DB::table('etapas_produccion as ep')
                ->join('ventanas as v', 'v.cotizacion_id', '=', 'ep.cotizacion_id')
                ->where('ep.etapa', $etapa)
                ->whereIn('ep.estado', ['pendiente', 'en_progreso'])
                ->where('ep.cotizacion_id', '!=', $cotizacionId)
                ->selectRaw('SUM((v.ancho/1000.0) * (v.alto/1000.0) * v.cantidad) as total')
                ->value('total') ?? 0;

            $velocidad = $velocidades[$etapa] ?? 15.0;
            $diasEtapa = ceil(((float) $backlogM2 + $m2Cotizacion) / $velocidad);
            $diasTotales += $diasEtapa;

            $detalle[$etapa] = [
                'backlog_m2' => round((float) $backlogM2, 2),
                'velocidad_m2_dia' => $velocidad,
                'dias_estimados' => $diasEtapa,
            ];
        }

        $fechaEstimada = now()->addWeekdays($diasTotales)->toDateString();

        return [
            'cotizacion_id'   => $cotizacionId,
            'm2_cotizacion'   => $m2Cotizacion,
            'dias_habiles_estimados' => $diasTotales,
            'fecha_estimada'  => $fechaEstimada,
            'detalle_por_etapa' => $detalle,
            'nota'            => count($velocidades) < 3 ? 'Velocidades basadas en estimación (sin suficiente historial aún)' : 'Basado en historial real',
        ];
    }

    /**
     * Calcula la velocidad real de producción (m²/día hábil) por etapa
     * a partir de las etapas completadas con fecha_inicio y fecha_fin_real.
     */
    private function calcularVelocidades(): array
    {
        $completadas = EtapaProduccion::whereNotNull('fecha_inicio')
            ->whereNotNull('fecha_fin_real')
            ->where('estado', 'completado')
            ->get();

        $acumulado = [];

        foreach ($completadas as $etapa) {
            $dias = Carbon::parse($etapa->fecha_inicio)->diffInWeekdays(Carbon::parse($etapa->fecha_fin_real)) ?: 1;
            $m2   = (float) $this->calcularM2($etapa->cotizacion_id);

            if ($m2 > 0 && $dias > 0) {
                $acumulado[$etapa->etapa][] = $m2 / $dias;
            }
        }

        $velocidades = [];

        foreach ($acumulado as $etapa => $values) {
            $velocidades[$etapa] = round(array_sum($values) / count($values), 2);
        }

        // Valores por defecto si no hay historial suficiente
        $defaults = [
            'corte_perfiles'        => 30.0,
            'corte_vidrio'          => 20.0,
            'fabricacion_termopanel' => 15.0,
            'armado'                => 20.0,
            'vidriado'              => 25.0,
            'junquillos'            => 30.0,
            'control'               => 40.0,
            'instalacion'           => 15.0,
            'entrega'               => 50.0,
        ];

        return array_merge($defaults, $velocidades);
    }
}
