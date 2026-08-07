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

    // Días hábiles de producción por defecto (para la entrega sugerida cuando aún no hay histórico)
    private const DIAS_PRODUCCION_DEFAULT = 15;

    // Cotizaciones aprobadas/en producción/entregadas con datos de operaciones
    public function index()
    {
        $cotizaciones = Cotizacion::with(['cliente', 'vendedor', 'estado', 'ventanas', 'detalles', 'historialEstados'])
            ->whereHas('estado', fn($q) => $q->whereNotIn('nombre', [
                'Evaluación', 'Rechazada', 'Anulada',
            ]))
            ->latest()
            ->get();

        $cotizacionIds = $cotizaciones->pluck('id');

        // Abono con lógica de "preconciliación":
        //  - Un documento emitido CON pago registrado (forma_pago o voucher Transbank)
        //    = la plata ya entró al emitir → cuenta como abono desde ya, aunque el banco
        //    aún no esté conciliado ("preconciliado"). La conciliación posterior
        //    (venta_movimiento / transbank_factura / cobro manual) NO cambia el monto,
        //    solo confirma el cuadre con el banco.
        //  - Un documento SIN pago registrado (crédito) cuenta solo lo conciliado.
        // 'falta_conciliar' = monto pagado al emitir que aún no cuadra con el banco.
        $docRows = DB::table('documentos_facturacion as df')
            ->leftJoin(DB::raw('(SELECT venta_id, SUM(monto) m FROM venta_movimiento GROUP BY venta_id) vm'), 'vm.venta_id', '=', 'df.id')
            ->leftJoin(DB::raw('(SELECT tf.documento_id, SUM(tt.monto_original) m
                                 FROM transbank_factura tf
                                 JOIN transbank_transacciones tt ON tt.id = tf.transaccion_id
                                 GROUP BY tf.documento_id) tbk'), 'tbk.documento_id', '=', 'df.id')
            ->where('df.estado', 'emitido')
            ->whereNotIn('df.tipo_documento_bsale_id', [2]) // sin notas de crédito
            ->whereIn('df.cotizacion_id', $cotizacionIds)
            ->selectRaw('df.cotizacion_id,
                df.monto,
                (df.forma_pago IS NOT NULL OR (df.nro_comprobante_transbank IS NOT NULL AND df.nro_comprobante_transbank <> "")) as pagado_emision,
                (COALESCE(vm.m,0) + COALESCE(tbk.m,0) + COALESCE(df.monto_cobrado_manual,0)) as conciliado')
            ->get();

        $cobrados  = [];
        $faltaConc = [];
        foreach ($docRows as $r) {
            $cid   = $r->cotizacion_id;
            $paid  = (int) $r->pagado_emision === 1;
            $monto = (float) $r->monto;
            $conc  = (float) $r->conciliado;
            $cobrados[$cid] = ($cobrados[$cid] ?? 0) + ($paid ? max($monto, $conc) : $conc);
            if ($paid && $conc < $monto) {
                $faltaConc[$cid] = ($faltaConc[$cid] ?? 0) + ($monto - $conc);
            }
        }

        $items = $cotizaciones->map(function ($c) use ($cobrados, $faltaConc) {
            $totalAbonado = (float) ($cobrados[$c->id] ?? 0);
            // cotizaciones.total está en NETO; el abono conciliado (venta_movimiento)
            // es BRUTO (plata real). Para que Total/Abono/Deuda cuadren, el total del
            // tablero va en BRUTO (neto × 1.19).
            $totalBruto = round((float) $c->total * 1.19);

            // App: ventanas viven en la tabla `ventanas`. Winperfil: viven en
            // `cotizacion_detalles` (esVidrio=false, con ancho_mm/alto_mm).
            if ($c->ventanas->isNotEmpty()) {
                $cantVentanas = $c->ventanas->sum('cantidad');
                $m2           = $c->ventanas->sum(fn($v) =>
                    ($v->ancho / 1000) * ($v->alto / 1000) * ($v->cantidad ?? 1)
                );
            } else {
                $ventDet = $c->detalles->filter(fn($d) =>
                    !$d->esVidrio && $d->ancho_mm && $d->alto_mm
                );
                $cantVentanas = $ventDet->sum('cantidad');
                $m2           = $ventDet->sum(fn($d) =>
                    ($d->ancho_mm / 1000) * ($d->alto_mm / 1000) * ($d->cantidad ?? 1)
                );
            }

            $tiempos = $this->tiempos($c);

            return [
                'id'                 => $c->id,
                'cliente'            => $c->cliente?->razon_social
                                    ?? trim(($c->cliente?->first_name ?? '') . ' ' . ($c->cliente?->last_name ?? '')),
                'vendedor'           => $c->vendedor?->name,
                'fecha'              => $c->fecha,
                'estado'             => $c->estado?->nombre,
                'total'              => $totalBruto,
                'total_abonado'      => $totalAbonado,
                'saldo'              => $totalBruto - $totalAbonado,
                'falta_conciliar'    => (float) ($faltaConc[$c->id] ?? 0),
                'pedido_proveedor'   => (bool) $c->pedido_proveedor,
                'estado_produccion'  => $c->estado_produccion,
                'fecha_entrega'      => $c->fecha_entrega,
                'notas_operaciones'  => $c->notas_operaciones,
                // Tablero de Operaciones
                'material'           => $c->material ?: 'PVC',
                'estado_obra'        => $c->estado_obra,
                'postventa'          => (bool) $c->postventa,
                'eett'               => $c->eett,
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

        // Promedio de días hábiles de producción (medición → instalación) de las ya instaladas
        $completadas = $items->whereNotNull('instalado_en')->pluck('dias_produccion')->filter(fn($d) => $d !== null);
        $promedio    = $completadas->count() ? round($completadas->avg(), 1) : null;

        // Entrega sugerida = medición + días hábiles de producción (promedio histórico o default)
        $leadDays = $promedio ? (int) round($promedio) : self::DIAS_PRODUCCION_DEFAULT;
        $items = $items->map(function ($it) use ($leadDays) {
            $it['entrega_sugerida'] = $it['medido_en']
                ? Carbon::parse($it['medido_en'])->addWeekdays($leadDays)->toDateString()
                : null;
            return $it;
        });

        $stats = [
            'total_cotizaciones' => $items->count(),
            'total_ventanas'     => $items->sum('cant_ventanas'),
            'total_m2'           => round($items->sum('m2'), 2),
            'total_facturado'    => $items->sum('total'),
            'total_abonado'      => $items->sum('total_abonado'),
            'total_saldo'        => $items->sum('saldo'),
            'dias_produccion_prom' => $promedio,
            'lead_days'          => $leadDays,
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
            // Tablero de Operaciones
            'material'          => 'sometimes|nullable|string|max:40',
            'estado_obra'       => 'sometimes|nullable|string|max:60',
            'postventa'         => 'sometimes|boolean',
            'eett'              => 'sometimes|nullable|string|max:120',
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $cotizacion->update($request->only([
            'pedido_proveedor', 'estado_produccion', 'fecha_entrega', 'notas_operaciones',
            'material', 'estado_obra', 'postventa', 'eett',
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

        // Tiempos en días HÁBILES (lunes a viernes), no corridos
        $diasProduccion = null;
        if ($medidoEn) {
            $fin = $instaladoEn ?? Carbon::now();
            $diasProduccion = (int) $medidoEn->copy()->startOfDay()->diffInWeekdays($fin->copy()->startOfDay());
        }

        // Días hábiles en el estado de producción actual (real, según último cambio)
        $ultimo = $prod->last();
        $diasEnEstado = $ultimo
            ? (int) $ultimo->fecha->copy()->startOfDay()->diffInWeekdays(Carbon::now()->startOfDay())
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
