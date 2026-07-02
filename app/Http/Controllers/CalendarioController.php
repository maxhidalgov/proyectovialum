<?php

namespace App\Http\Controllers;

use App\Models\AusenciaEmpleado;
use App\Models\Cotizacion;
use App\Models\Recordatorio;
use App\Models\VisitaCliente;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    /**
     * Retorna todos los eventos del calendario en el rango dado,
     * unificando recordatorios, visitas, entregas y ausencias.
     * Formato compatible con FullCalendar.
     */
    public function eventos(Request $request): JsonResponse
    {
        $desde = $request->input('start', now()->startOfMonth()->toDateString());
        $hasta = $request->input('end', now()->endOfMonth()->toDateString());

        // Qué fuentes incluir (por defecto todas)
        $fuentes = $request->input('fuentes', ['recordatorio', 'visita', 'entrega', 'ausencia']);
        $fuentes = is_array($fuentes) ? $fuentes : explode(',', $fuentes);

        $eventos = [];

        if (in_array('recordatorio', $fuentes)) {
            $eventos = array_merge($eventos, $this->eventosRecordatorios($desde, $hasta));
        }
        if (in_array('visita', $fuentes)) {
            $eventos = array_merge($eventos, $this->eventosVisitas($desde, $hasta));
        }
        if (in_array('entrega', $fuentes)) {
            $eventos = array_merge($eventos, $this->eventosEntregas($desde, $hasta));
        }
        if (in_array('ausencia', $fuentes)) {
            $eventos = array_merge($eventos, $this->eventosAusencias($desde, $hasta));
        }

        return response()->json($eventos);
    }

    // ── Recordatorios ───────────────────────────────────────────────────────

    private function eventosRecordatorios(string $desde, string $hasta): array
    {
        return Recordatorio::with(['cliente', 'empleado'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'cancelado')
            ->get()
            ->map(function ($r) {
                $start = $r->fecha->toDateString();
                $allDay = true;
                if ($r->hora) {
                    $start .= 'T' . $r->hora;
                    $allDay = false;
                }

                return [
                    'id'       => 'rec_' . $r->id,
                    'title'    => $this->iconoTipo($r->tipo) . ' ' . $r->titulo,
                    'start'    => $start,
                    'allDay'   => $allDay,
                    'backgroundColor' => $r->estado === 'completado' ? '#9e9e9e' : $this->colorTipo($r->tipo),
                    'borderColor'     => $r->estado === 'completado' ? '#9e9e9e' : $this->colorTipo($r->tipo),
                    'extendedProps'   => [
                        'fuente'        => 'recordatorio',
                        'recordatorio_id' => $r->id,
                        'tipo'          => $r->tipo,
                        'estado'        => $r->estado,
                        'descripcion'   => $r->descripcion,
                        'cliente'       => $this->nombreCliente($r->cliente),
                        'empleado'      => $r->empleado?->nombre,
                        'cotizacion_id' => $r->cotizacion_id,
                        'editable'      => true,
                    ],
                ];
            })->toArray();
    }

    // ── Visitas ─────────────────────────────────────────────────────────────

    private function eventosVisitas(string $desde, string $hasta): array
    {
        return VisitaCliente::with(['cliente', 'cotizacion.cliente', 'empleados'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'cancelada')
            ->get()
            ->map(function ($v) {
                $start = $v->fecha->toDateString();
                $allDay = true;
                if ($v->hora) {
                    $start .= 'T' . $v->hora;
                    $allDay = false;
                }

                $cliente = $this->nombreCliente($v->cliente) ?? $this->nombreCliente($v->cotizacion?->cliente);
                $empleados = $v->empleados->pluck('nombre')->implode(', ');

                return [
                    'id'       => 'vis_' . $v->id,
                    'title'    => '📍 ' . ucfirst($v->tipo) . ($cliente ? " — {$cliente}" : ''),
                    'start'    => $start,
                    'allDay'   => $allDay,
                    'backgroundColor' => '#2196f3',
                    'borderColor'     => '#2196f3',
                    'extendedProps'   => [
                        'fuente'        => 'visita',
                        'visita_id'     => $v->id,
                        'tipo'          => $v->tipo,
                        'estado'        => $v->estado,
                        'cliente'       => $cliente,
                        'empleados'     => $empleados,
                        'notas'         => $v->notas,
                        'cotizacion_id' => $v->cotizacion_id,
                        'editable'      => false,
                    ],
                ];
            })->toArray();
    }

    // ── Entregas (fecha_entrega de cotizaciones) ────────────────────────────

    private function eventosEntregas(string $desde, string $hasta): array
    {
        return Cotizacion::with('cliente')
            ->whereNotNull('fecha_entrega')
            ->whereBetween('fecha_entrega', [$desde, $hasta])
            ->get()
            ->map(function ($c) {
                $cliente = $this->nombreCliente($c->cliente);
                $vencida = Carbon::parse($c->fecha_entrega)->isPast()
                    && $c->estado_produccion !== 'Fabricadas OK'
                    && $c->estado_produccion !== 'Instalada';

                return [
                    'id'       => 'ent_' . $c->id,
                    'title'    => '🚚 Entrega #' . $c->id . ($cliente ? " — {$cliente}" : ''),
                    'start'    => Carbon::parse($c->fecha_entrega)->toDateString(),
                    'allDay'   => true,
                    'backgroundColor' => $vencida ? '#f44336' : '#ff9800',
                    'borderColor'     => $vencida ? '#f44336' : '#ff9800',
                    'extendedProps'   => [
                        'fuente'        => 'entrega',
                        'cotizacion_id' => $c->id,
                        'cliente'       => $cliente,
                        'estado_produccion' => $c->estado_produccion,
                        'vencida'       => $vencida,
                        'editable'      => false,
                    ],
                ];
            })->toArray();
    }

    // ── Ausencias ───────────────────────────────────────────────────────────

    private function eventosAusencias(string $desde, string $hasta): array
    {
        return AusenciaEmpleado::with('empleado')
            ->whereBetween('fecha', [$desde, $hasta])
            ->get()
            ->map(function ($a) {
                return [
                    'id'       => 'aus_' . $a->id,
                    'title'    => '🏠 ' . ($a->empleado?->nombre ?? 'Empleado') . ' — ' . $this->labelTipoAusencia($a->tipo),
                    'start'    => $a->fecha->toDateString(),
                    'allDay'   => true,
                    'backgroundColor' => '#ffc107',
                    'borderColor'     => '#ffc107',
                    'textColor'       => '#000',
                    'extendedProps'   => [
                        'fuente'      => 'ausencia',
                        'ausencia_id' => $a->id,
                        'empleado'    => $a->empleado?->nombre,
                        'tipo'        => $a->tipo,
                        'motivo'      => $a->motivo,
                        'editable'    => false,
                    ],
                ];
            })->toArray();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function nombreCliente($cliente): ?string
    {
        if (!$cliente) return null;
        $nombre = $cliente->razon_social
            ?? trim(($cliente->first_name ?? '') . ' ' . ($cliente->last_name ?? ''));
        return $nombre ?: null;
    }

    private function colorTipo(string $tipo): string
    {
        return match ($tipo) {
            'llamada'     => '#4caf50',
            'reunion'     => '#673ab7',
            'pago'        => '#e91e63',
            'seguimiento' => '#00bcd4',
            'entrega'     => '#ff9800',
            default       => '#4caf50',
        };
    }

    private function iconoTipo(string $tipo): string
    {
        return match ($tipo) {
            'llamada'     => '📞',
            'reunion'     => '👥',
            'pago'        => '💰',
            'seguimiento' => '🔄',
            'entrega'     => '🚚',
            default       => '✓',
        };
    }

    private function labelTipoAusencia(string $tipo): string
    {
        return match ($tipo) {
            'dia_completo'  => 'Día completo',
            'media_manana'  => 'Media mañana',
            'media_tarde'   => 'Media tarde',
            'llegada_tarde' => 'Llegada tarde',
            default         => $tipo,
        };
    }
}
