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
        $incluirOcultos = filter_var(request('incluir_ocultos', false), FILTER_VALIDATE_BOOLEAN);
        $cotizaciones = Cotizacion::with(['cliente', 'vendedor', 'estado', 'ventanas', 'detalles', 'historialEstados'])
            ->whereHas('estado', fn($q) => $q->whereNotIn('nombre', [
                'Evaluación', 'Rechazada', 'Anulada',
            ]))
            ->when(!$incluirOcultos, fn($q) => $q->where(fn($w) => $w->where('oculto_operaciones', false)->orWhereNull('oculto_operaciones')))
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

        // Abonos manuales (proyecto_abonos) por cotización → suma
        $abonosManual = DB::table('proyecto_abonos')
            ->whereIn('cotizacion_id', $cotizacionIds)
            ->groupBy('cotizacion_id')
            ->selectRaw('cotizacion_id, SUM(monto) as total')
            ->pluck('total', 'cotizacion_id');

        $items = $cotizaciones->map(function ($c) use ($cobrados, $faltaConc, $abonosManual) {
            $esManual = (bool) $c->es_manual;

            // Manual: total ya es BRUTO (lo que paga el cliente); abono = suma de sus
            // abonos (proyecto_abonos). Cotización normal: total NETO ×1.19 y abono
            // derivado de facturas/conciliación.
            if ($esManual) {
                $totalBruto   = (float) $c->total;
                $totalAbonado = (float) ($abonosManual[$c->id] ?? 0);
                $faltaCon     = 0.0;
            } else {
                $totalBruto   = round((float) $c->total * 1.19);
                $totalAbonado = (float) ($cobrados[$c->id] ?? 0);
                $faltaCon     = (float) ($faltaConc[$c->id] ?? 0);
            }

            // Cant/M²: manual → campos propios; cotización → ventanas/detalles.
            if ($esManual) {
                $cantVentanas = (int) ($c->cant_manual ?? 0);
                $m2           = (float) ($c->m2_manual ?? 0);
            } elseif ($c->ventanas->isNotEmpty()) {
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

            $nombre = $esManual
                ? ($c->nombre_manual ?: 'Proyecto manual')
                : ($c->cliente?->razon_social ?? trim(($c->cliente?->first_name ?? '') . ' ' . ($c->cliente?->last_name ?? '')));

            return [
                'id'                 => $c->id,
                'es_manual'          => $esManual,
                'cliente'            => $nombre,
                'vendedor'           => $c->vendedor?->name,
                'fecha'              => $c->fecha,
                'estado'             => $c->estado?->nombre,
                'total'              => $totalBruto,
                'total_abonado'      => $totalAbonado,
                'saldo'              => $totalBruto - $totalAbonado,
                'falta_conciliar'    => $faltaCon,
                'pedido_proveedor'   => (bool) $c->pedido_proveedor,
                'estado_produccion'  => $c->estado_produccion,
                'fecha_entrega'      => $c->fecha_entrega,
                'notas_operaciones'  => $c->notas_operaciones,
                // Tablero de Operaciones
                'material'           => $c->material ?: 'PVC',
                'estado_obra'        => $c->estado_obra,
                'postventa'          => (bool) $c->postventa,
                'eett'               => $c->eett,
                'oculto_operaciones' => (bool) $c->oculto_operaciones,
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

        $ocultosCount = Cotizacion::whereHas('estado', fn($q) => $q->whereNotIn('nombre', [
                'Evaluación', 'Rechazada', 'Anulada',
            ]))->where('oculto_operaciones', true)->count();

        return response()->json([
            'cotizaciones'  => $items,
            'stats'         => $stats,
            'ocultos_count' => $ocultosCount,
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
            'oculto_operaciones'=> 'sometimes|boolean',
            // Proyectos manuales (editables solo en filas manuales)
            'nombre_manual'     => 'sometimes|nullable|string|max:150',
            'total'             => 'sometimes|numeric|min:0',
            'abono_manual'      => 'sometimes|numeric|min:0',
            'cant_manual'       => 'sometimes|nullable|integer|min:0',
            'm2_manual'         => 'sometimes|nullable|numeric|min:0',
            'fecha'             => 'sometimes|date',
        ]);

        $cotizacion = Cotizacion::findOrFail($id);

        $campos = [
            'pedido_proveedor', 'estado_produccion', 'fecha_entrega', 'notas_operaciones',
            'material', 'estado_obra', 'postventa', 'eett', 'oculto_operaciones',
        ];
        // Campos manuales solo se aceptan si el proyecto es manual
        if ($cotizacion->es_manual) {
            $campos = array_merge($campos, ['nombre_manual', 'total', 'abono_manual', 'cant_manual', 'm2_manual', 'fecha']);
        }

        $cotizacion->update($request->only($campos));

        return response()->json(['success' => true]);
    }

    /**
     * Crear un proyecto manual en el tablero (sin cotización real). Se guarda como
     * una cotización con es_manual=true para reusar todo el módulo de operaciones.
     */
    public function storeManual(Request $request)
    {
        $data = $request->validate([
            'nombre_manual'     => 'required|string|max:150',
            'material'          => 'nullable|string|max:40',
            'total'             => 'nullable|numeric|min:0',
            'abono_manual'      => 'nullable|numeric|min:0',
            'cant_manual'       => 'nullable|integer|min:0',
            'm2_manual'         => 'nullable|numeric|min:0',
            'eett'              => 'nullable|string|max:120',
            'fecha'             => 'nullable|date',
            'estado_produccion' => 'nullable|in:En Espera de Medidas,Lista para Corte,En Fabricación,Fabricadas OK,Instalada',
        ]);

        // Estado que pasa el filtro de operaciones (no Evaluación/Rechazada/Anulada)
        $estadoId = optional(\App\Models\EstadoCotizacion::where('nombre', 'Aprobada')->first())->id
                 ?? \App\Models\EstadoCotizacion::whereNotIn('nombre', ['Evaluación', 'Rechazada', 'Anulada'])->value('id');

        $cot = Cotizacion::create([
            'es_manual'           => true,
            'nombre_manual'       => $data['nombre_manual'],
            'cliente_id'          => null,
            'vendedor_id'         => auth()->id() ?? 1,
            'estado_cotizacion_id'=> $estadoId,
            'fecha'               => $data['fecha'] ?? now()->toDateString(),
            'total'               => $data['total'] ?? 0,      // BRUTO (lo que paga el cliente)
            'abono_manual'        => $data['abono_manual'] ?? 0,
            'cant_manual'         => $data['cant_manual'] ?? null,
            'm2_manual'           => $data['m2_manual'] ?? null,
            'material'            => $data['material'] ?? 'PVC',
            'eett'                => $data['eett'] ?? null,
            'estado_produccion'   => $data['estado_produccion'] ?? null,
        ]);

        // El abono inicial se guarda como un registro en proyecto_abonos (con fecha)
        if (($data['abono_manual'] ?? 0) > 0) {
            DB::table('proyecto_abonos')->insert([
                'cotizacion_id' => $cot->id,
                'fecha'         => $cot->fecha,
                'monto'         => $data['abono_manual'],
                'nota'          => 'Abono inicial',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        return response()->json(['success' => true, 'id' => $cot->id]);
    }

    /**
     * Borrar un proyecto manual (solo manuales; las cotizaciones reales no se borran aquí).
     */
    public function destroyManual($id)
    {
        $cot = Cotizacion::where('id', $id)->where('es_manual', true)->firstOrFail();
        $cot->delete(); // proyecto_abonos cae por FK cascade

        return response()->json(['success' => true]);
    }

    /**
     * Detalle de abonos de un proyecto (para el modal clickeable).
     * Manual → registros de proyecto_abonos (editables). No manual → abonos reales
     * de las facturas: transferencia (venta_movimiento), tarjeta (transbank) y cobro
     * manual, cada uno con su fecha/fuente (solo lectura).
     */
    public function abonosDetalle($id)
    {
        $cot = Cotizacion::findOrFail($id);

        if ($cot->es_manual) {
            $abonos = DB::table('proyecto_abonos')
                ->where('cotizacion_id', $id)
                ->orderBy('fecha')
                ->get(['id', 'fecha', 'monto', 'nota'])
                ->map(fn ($a) => [
                    'id'     => $a->id,
                    'fecha'  => $a->fecha,
                    'monto'  => (float) $a->monto,
                    'fuente' => $a->nota ?: 'Abono',
                    'editable' => true,
                ]);
            return response()->json(['es_manual' => true, 'abonos' => $abonos->values()]);
        }

        // No manual: reunir abonos reales de los documentos de la cotización
        $docIds = DB::table('documentos_facturacion')
            ->where('cotizacion_id', $id)->where('estado', 'emitido')
            ->whereNotIn('tipo_documento_bsale_id', [2])->pluck('id');

        $abonos = collect();
        if ($docIds->isNotEmpty()) {
            foreach (DB::table('venta_movimiento as vm')
                ->join('movimientos_bancarios as mb', 'mb.id', '=', 'vm.movimiento_id')
                ->whereIn('vm.venta_id', $docIds)
                ->get(['vm.monto', 'mb.fecha_contable']) as $r) {
                $abonos->push(['fecha' => substr((string) $r->fecha_contable, 0, 10), 'monto' => (float) $r->monto, 'fuente' => 'Transferencia', 'editable' => false]);
            }
            foreach (DB::table('transbank_factura as tf')
                ->join('transbank_transacciones as tt', 'tt.id', '=', 'tf.transaccion_id')
                ->whereIn('tf.documento_id', $docIds)
                ->get(['tt.monto_original', 'tt.fecha_movimiento']) as $r) {
                $abonos->push(['fecha' => substr((string) $r->fecha_movimiento, 0, 10), 'monto' => (float) $r->monto_original, 'fuente' => 'Tarjeta / Transbank', 'editable' => false]);
            }
            foreach (DB::table('documentos_facturacion')
                ->whereIn('id', $docIds)->where('monto_cobrado_manual', '>', 0)
                ->get(['monto_cobrado_manual', 'cobrado_manual_nota', 'fecha_emision']) as $r) {
                $abonos->push(['fecha' => substr((string) $r->fecha_emision, 0, 10), 'monto' => (float) $r->monto_cobrado_manual, 'fuente' => $r->cobrado_manual_nota ?: 'Cobro manual', 'editable' => false]);
            }
        }

        return response()->json(['es_manual' => false, 'abonos' => $abonos->sortBy('fecha')->values()]);
    }

    /** Agregar un abono manual (solo proyectos manuales). */
    public function storeAbono(Request $request, $id)
    {
        $data = $request->validate([
            'fecha' => 'required|date',
            'monto' => 'required|numeric|min:1',
            'nota'  => 'nullable|string|max:150',
        ]);

        $cot = Cotizacion::where('id', $id)->where('es_manual', true)->firstOrFail();

        $abonoId = DB::table('proyecto_abonos')->insertGetId([
            'cotizacion_id' => $cot->id,
            'fecha'         => $data['fecha'],
            'monto'         => $data['monto'],
            'nota'          => $data['nota'] ?? null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json(['success' => true, 'id' => $abonoId]);
    }

    /** Borrar un abono manual. */
    public function destroyAbono($abonoId)
    {
        DB::table('proyecto_abonos')->where('id', $abonoId)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Vincular un proyecto manual con una cotización real: transfiere el seguimiento
     * (estado producción, pedido proveedor, postventa, material, EETT, notas) a la
     * cotización y borra el manual (para que no quede duplicado).
     */
    public function vincularCotizacion(Request $request, $id)
    {
        $data = $request->validate(['cotizacion_id' => 'required|exists:cotizaciones,id']);

        $manual = Cotizacion::where('id', $id)->where('es_manual', true)->firstOrFail();
        $real   = Cotizacion::findOrFail($data['cotizacion_id']);

        // Transferir los campos de seguimiento no vacíos del manual a la cotización real
        $transfer = [];
        foreach (['estado_produccion', 'pedido_proveedor', 'postventa', 'material', 'eett', 'notas_operaciones'] as $campo) {
            if (!empty($manual->$campo)) {
                $transfer[$campo] = $manual->$campo;
            }
        }
        if ($transfer) {
            $real->update($transfer);
        }

        $manual->delete(); // borra el manual + sus proyecto_abonos (cascade)

        return response()->json(['success' => true, 'cotizacion_id' => $real->id]);
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
