<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\CotizacionEstadoHistorial;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperacionesController extends Controller
{
    // Hito que marca el inicio del reloj de producción (T0)
    private const ESTADO_MEDICION = 'Lista para Corte';
    private const ESTADO_INSTALADA = 'Instalada';

    // Cotizaciones aprobadas/en producción/entregadas con datos de operaciones
    public function index()
    {
        $cotizaciones = Cotizacion::with(['cliente', 'vendedor', 'estado', 'ventanas', 'historialEstados'])
            ->whereHas('estado', fn($q) => $q->whereNotIn('nombre', [
                'Evaluación', 'Rechazada', 'Anulada',
            ]))
            ->latest()
            ->get();

        $cotizacionIds = $cotizaciones->pluck('id');

        // Abonado = lo conciliado del banco contra documentos de cada cotización
        $cobrados = DB::table('venta_movimiento as vm')
            ->join('documentos_facturacion as df', 'df.id', '=', 'vm.venta_id')
            ->whereIn('df.cotizacion_id', $cotizacionIds)
            ->groupBy('df.cotizacion_id')
            ->selectRaw('df.cotizacion_id, SUM(vm.monto) as total')
            ->pluck('total', 'cotizacion_id');

        $items = $cotizaciones->map(function ($c) use ($cobrados) {
            $totalAbonado = (float) ($cobrados[$c->id] ?? 0);
            $cantVentanas = $c->ventanas->sum('cantidad');
            $m2           = $c->ventanas->sum(fn($v) =>
                ($v->ancho / 1000) * ($v->alto / 1000) * ($v->cantidad ?? 1)
            );

            $tiempos = $this->tiempos($c);

            return [
                'id'                 => $c->id,
                'cliente'            => $c->cliente?->razon_social
                                    ?? trim(($c->cliente?->first_name ?? '') . ' ' . ($c->cliente?->last_name ?? '')),
                'vendedor'           => $c->vendedor?->name,
                'fecha'              => $c->fecha,
                'estado'             => $c->estado?->nombre,
                'total'              => (float) $c->total,
                'total_abonado'      => $totalAbonado,
                'saldo'              => (float) ($c->total - $totalAbonado),
                'pedido_proveedor'   => (bool) $c->pedido_proveedor,
                'estado_produccion'  => $c->estado_produccion,
                'fecha_entrega'      => $c->fecha_entrega,
                'notas_operaciones'  => $c->notas_operaciones,
                'cant_ventanas'      => (int) $cantVentanas,
                'm2'                 => round($m2, 2),
                // Métricas de tiempo (T0 = medición)
                'medido_en'          => $tiempos['medido_en'],
                'instalado_en'       => $tiempos['instalado_en'],
                'dias_produccion'    => $tiempos['dias_produccion'],
                'dias_en_estado'     => $tiempos['dias_en_estado'],
                'timeline'           => $tiempos['timeline'],
            ];
        });

        // Promedio de días de producción (medición → instalación) de las ya instaladas
        $completadas = $items->whereNotNull('dias_produccion')->pluck('dias_produccion');

        $stats = [
            'total_cotizaciones' => $items->count(),
            'total_ventanas'     => $items->sum('cant_ventanas'),
            'total_m2'           => round($items->sum('m2'), 2),
            'total_facturado'    => $items->sum('total'),
            'total_abonado'      => $items->sum('total_abonado'),
            'total_saldo'        => $items->sum('saldo'),
            'dias_produccion_prom' => $completadas->count() ? round($completadas->avg(), 1) : null,
        ];

        return response()->json([
            'cotizaciones' => $items,
            'stats'        => $stats,
        ]);
    }

    // Actualizar campos de operaciones inline
    public function update(Request $request, $id)
    {
        $request->validate([
            'pedido_proveedor'  => 'sometimes|boolean',
            'estado_produccion' => 'sometimes|nullable|in:En Espera de Medidas,Lista para Corte,En Fabricación,Fabricadas OK,Instalada',
            'fecha_entrega'     => 'sometimes|nullable|date',
            'notas_operaciones' => 'sometimes|nullable|string|max:1000',
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $cotizacion->update($request->only([
            'pedido_proveedor', 'estado_produccion', 'fecha_entrega', 'notas_operaciones',
        ]));

        return response()->json(['success' => true]);
    }

    /**
     * Editar la fecha de un hito del historial (ej: corregir el día real de medición).
     */
    public function actualizarHistorial(Request $request, $id)
    {
        $request->validate(['fecha' => 'required|date']);

        $registro = CotizacionEstadoHistorial::findOrFail($id);
        $registro->update(['fecha' => $request->fecha]);

        return response()->json(['success' => true]);
    }

    /**
     * Agregar un hito manualmente (para poner al día trabajos ya en curso con sus fechas reales).
     */
    public function storeHistorial(Request $request, $id)
    {
        $data = $request->validate([
            'estado' => 'required|in:En Espera de Medidas,Lista para Corte,En Fabricación,Fabricadas OK,Instalada',
            'fecha'  => 'required|date',
        ]);

        Cotizacion::findOrFail($id); // asegura que exista

        $registro = CotizacionEstadoHistorial::create([
            'cotizacion_id' => $id,
            'tipo'          => 'produccion',
            'estado'        => $data['estado'],
            'fecha'         => $data['fecha'],
        ]);

        return response()->json(['success' => true, 'id' => $registro->id]);
    }

    /**
     * Borrar un hito del historial (corrección de errores al poner al día).
     */
    public function destroyHistorial($id)
    {
        CotizacionEstadoHistorial::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Calcula las métricas de tiempo de una cotización a partir de su historial de estados.
     * T0 = medición (entrada a "Lista para Corte").
     */
    private function tiempos(Cotizacion $c): array
    {
        $prod = $c->historialEstados
            ->where('tipo', 'produccion')
            ->sortBy('fecha')
            ->values();

        $medidoEn    = optional($prod->firstWhere('estado', self::ESTADO_MEDICION))->fecha;
        $instaladoEn = optional($prod->firstWhere('estado', self::ESTADO_INSTALADA))->fecha;

        $diasProduccion = null;
        if ($medidoEn) {
            $fin = $instaladoEn ?? Carbon::now();
            $diasProduccion = (int) $medidoEn->copy()->startOfDay()->diffInDays($fin->copy()->startOfDay());
        }

        // Días en el estado de producción actual (real, según último cambio)
        $ultimo = $prod->last();
        $diasEnEstado = $ultimo
            ? (int) $ultimo->fecha->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay())
            : null;

        $timeline = $c->historialEstados
            ->sortBy('fecha')
            ->values()
            ->map(fn ($h) => [
                'id'              => $h->id,
                'tipo'            => $h->tipo,
                'estado'          => $h->estado,
                'estado_anterior' => $h->estado_anterior,
                'fecha'           => $h->fecha?->toDateTimeString(),
            ]);

        return [
            'medido_en'       => $medidoEn?->toDateString(),
            'instalado_en'    => $instaladoEn?->toDateString(),
            'dias_produccion' => $diasProduccion,
            'dias_en_estado'  => $diasEnEstado,
            'timeline'        => $timeline,
        ];
    }
}
