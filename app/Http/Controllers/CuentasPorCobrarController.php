<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentasPorCobrarController extends Controller
{
    // tipo_documento_bsale_id = 2 → Nota de Crédito de venta
    private const TIPO_NC = 2;

    // Subquery de monto cobrado efectivo por documento de venta:
    // facturas normales: cobrado banco + NC aplicada sobre esta factura
    // NCs (tipo 2)     : cobrado banco + monto de esta NC ya aplicado a alguna factura
    private function efectivoCobradoSub(): \Illuminate\Database\Query\Expression
    {
        return DB::raw("(
            SELECT
                df.id AS df_id,
                CASE
                  -- Chipax solo aplica como fallback cuando no hay ningún pago explícito
                  WHEN df.chipax_monto_por_cobrar IS NOT NULL
                   AND COALESCE(vm.monto_cobrado, 0) = 0
                   AND COALESCE(tbk.monto_tbk, 0) = 0
                   AND COALESCE(df.monto_cobrado_manual, 0) = 0
                    THEN df.monto - df.chipax_monto_por_cobrar
                  ELSE
                    COALESCE(vm.monto_cobrado, 0)
                      + COALESCE(tbk.monto_tbk, 0)
                      + COALESCE(df.monto_cobrado_manual, 0)
                      + CASE WHEN df.tipo_documento_bsale_id = 2
                             THEN COALESCE(ncnc.monto_nc, 0)
                             ELSE COALESCE(ncf.monto_nc, 0)
                        END
                END AS monto_cobrado_efectivo
            FROM documentos_facturacion df
            LEFT JOIN (SELECT venta_id, SUM(monto) monto_cobrado FROM venta_movimiento GROUP BY venta_id) vm
                   ON vm.venta_id = df.id
            LEFT JOIN (SELECT tf.documento_id, SUM(tt.monto_original) monto_tbk
                       FROM transbank_factura tf
                       JOIN transbank_transacciones tt ON tt.id = tf.transaccion_id
                       GROUP BY tf.documento_id) tbk
                   ON tbk.documento_id = df.id
            LEFT JOIN (SELECT factura_id AS df_id, SUM(monto) monto_nc FROM venta_nc_aplicacion GROUP BY factura_id) ncf
                   ON ncf.df_id = df.id
            LEFT JOIN (SELECT nc_id AS df_id, SUM(monto) monto_nc FROM venta_nc_aplicacion GROUP BY nc_id) ncnc
                   ON ncnc.df_id = df.id
        ) AS ec");
    }

    // Resumen por cliente
    public function index(Request $request)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $buscar         = $request->get('buscar');
        $monto          = $request->get('monto');
        $soloPendientes = filter_var($request->get('solo_pendientes', true), FILTER_VALIDATE_BOOLEAN);

        $q = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c',  'c.id',      '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_cot', 'cl_cot.id', '=', 'c.cliente_id')
            ->leftJoin('clientes as cl_dir', 'cl_dir.id', '=', 'df.cliente_id')
            ->leftJoin($this->efectivoCobradoSub(), 'ec.df_id', '=', 'df.id')
            ->where('df.estado', 'emitido')
            ->whereNotIn('df.tipo_documento_bsale_id', [1])
            ->select(
                DB::raw('COALESCE(cl_dir.id, cl_cot.id) as cliente_id'),
                DB::raw('COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre) as razon_social'),
                DB::raw('COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut) as identification'),
                DB::raw('COUNT(df.id) as cantidad_docs'),
                DB::raw('COUNT(CASE WHEN df.tipo_documento_bsale_id = 2 THEN 1 END) as cantidad_ncs'),
                DB::raw('SUM(CASE WHEN df.tipo_documento_bsale_id = 2 THEN -df.monto ELSE df.monto END) as total_facturado'),
                DB::raw('SUM(CASE WHEN df.tipo_documento_bsale_id = 2
                                  THEN -COALESCE(ec.monto_cobrado_efectivo,0)
                                  ELSE  COALESCE(ec.monto_cobrado_efectivo,0) END) as total_cobrado'),
                DB::raw('SUM(CASE WHEN df.tipo_documento_bsale_id = 2
                                  THEN -(df.monto - COALESCE(ec.monto_cobrado_efectivo,0))
                                  ELSE  (df.monto - COALESCE(ec.monto_cobrado_efectivo,0)) END) as total_pendiente'),
                DB::raw("SUM(CASE WHEN df.nc_revision_estado = 'requiere_revision' THEN 1 ELSE 0 END) as facturas_por_revisar")
            )
            ->groupBy(
                DB::raw('COALESCE(cl_dir.id, cl_cot.id)'),
                DB::raw('COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre)'),
                DB::raw('COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut)')
            );

        if ($desde) $q->where('df.fecha_emision', '>=', $desde);
        if ($hasta) $q->where('df.fecha_emision', '<=', $hasta);
        if ($buscar) {
            $q->where(function ($sq) use ($buscar) {
                $sq->where('cl_cot.razon_social', 'like', "%$buscar%")
                   ->orWhere('cl_dir.razon_social', 'like', "%$buscar%")
                   ->orWhere('df.bsale_cliente_nombre', 'like', "%$buscar%")
                   ->orWhere('cl_cot.identification', 'like', "%$buscar%")
                   ->orWhere('cl_dir.identification', 'like', "%$buscar%")
                   ->orWhere('df.bsale_cliente_rut', 'like', "%$buscar%");
            });
        }
        if ($monto) {
            $montoNum = (int) preg_replace('/[^0-9]/', '', $monto);
            if ($montoNum > 0) $q->where('df.monto', $montoNum);
        }
        if ($soloPendientes) {
            $q->havingRaw('total_pendiente > 0');
        }

        $clientes = $q->orderByDesc('facturas_por_revisar')->orderByDesc('total_pendiente')->get();

        $totales = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c',  'c.id',      '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_cot', 'cl_cot.id', '=', 'c.cliente_id')
            ->leftJoin('clientes as cl_dir', 'cl_dir.id', '=', 'df.cliente_id')
            ->leftJoin($this->efectivoCobradoSub(), 'ec.df_id', '=', 'df.id')
            ->where('df.estado', 'emitido')
            ->whereNotIn('df.tipo_documento_bsale_id', [1])
            ->when($desde, fn($sq) => $sq->where('df.fecha_emision', '>=', $desde))
            ->when($hasta, fn($sq) => $sq->where('df.fecha_emision', '<=', $hasta))
            ->selectRaw("
                COUNT(df.id) as total_docs,
                COUNT(CASE WHEN df.tipo_documento_bsale_id = 2 THEN 1 END) as total_ncs,
                COUNT(DISTINCT COALESCE(cl_dir.id, cl_cot.id)) as total_clientes,
                SUM(CASE WHEN df.tipo_documento_bsale_id = 2 THEN -df.monto ELSE df.monto END) as total_facturado,
                SUM(CASE WHEN df.tipo_documento_bsale_id = 2
                         THEN -COALESCE(ec.monto_cobrado_efectivo,0)
                         ELSE  COALESCE(ec.monto_cobrado_efectivo,0) END) as total_cobrado,
                SUM(CASE WHEN df.tipo_documento_bsale_id = 2
                         THEN -(df.monto - COALESCE(ec.monto_cobrado_efectivo,0))
                         ELSE  (df.monto - COALESCE(ec.monto_cobrado_efectivo,0)) END) as total_pendiente,
                SUM(CASE WHEN df.nc_revision_estado = 'requiere_revision' THEN 1 ELSE 0 END) as facturas_por_revisar
            ")
            ->first();

        // Filas sintéticas de boletas por período (una por mes desde boleta_resumenes)
        $boletaPeriodos = $this->boletaPeriodosResumen($desde, $hasta, $soloPendientes);

        // Sumar boletas al KPI de totales (sin filtro solo_pendientes, igual que la query SQL)
        $boletaParaKpi = $soloPendientes
            ? $this->boletaPeriodosResumen($desde, $hasta, false)
            : $boletaPeriodos;
        $totales->total_pendiente = (float)($totales->total_pendiente ?? 0) + $boletaParaKpi->sum('total_pendiente');
        $totales->total_facturado = (float)($totales->total_facturado ?? 0) + $boletaParaKpi->sum('total_facturado');
        $totales->total_cobrado   = (float)($totales->total_cobrado   ?? 0) + $boletaParaKpi->sum('total_cobrado');
        $totales->total_docs      = (int)($totales->total_docs        ?? 0) + $boletaParaKpi->count();

        $clientesConBoletas = collect($boletaPeriodos)
            ->merge($clientes)
            ->sortByDesc('total_pendiente')
            ->values();

        return response()->json(['clientes' => $clientesConBoletas, 'totales' => $totales]);
    }

    private function boletaPeriodosResumen(?string $desde, ?string $hasta, bool $soloPendientes): \Illuminate\Support\Collection
    {
        // Cobrado vía movimientos bancarios a nivel periodo (Conciliacion module)
        $cobradoPorPeriodo = DB::table('boleta_periodo_movimiento')
            ->selectRaw('periodo, SUM(monto) as cobrado')
            ->groupBy('periodo')
            ->pluck('cobrado', 'periodo');

        // Cobrado vía conciliación por forma_pago desde CPC (boleta_resumen_movimiento)
        $cobradoResumenPorPeriodo = DB::table('boleta_resumenes as br')
            ->join('boleta_resumen_movimiento as brm', 'brm.boleta_resumen_id', '=', 'br.id')
            ->selectRaw('br.periodo, SUM(brm.monto) as cobrado')
            ->groupBy('br.periodo')
            ->pluck('cobrado', 'periodo');

        // Cobrado vía Transbank (boletas marcadas conciliado_transbank = true)
        $cobradoTransbankPorPeriodo = DB::table('boleta_resumenes')
            ->where('conciliado_transbank', true)
            ->selectRaw('periodo, SUM(monto_total) as cobrado')
            ->groupBy('periodo')
            ->pluck('cobrado', 'periodo');

        $periodos = DB::table('boleta_resumenes')
            ->selectRaw("periodo, SUM(total_boletas) as total_boletas, SUM(monto_total) as monto_total")
            ->groupBy('periodo')
            ->orderByDesc('periodo')
            ->get();

        return $periodos
            ->when($desde, fn($c) => $c->filter(fn($p) => $p->periodo >= substr($desde, 0, 7)))
            ->when($hasta, fn($c) => $c->filter(fn($p) => $p->periodo <= substr($hasta, 0, 7)))
            ->map(function ($p) use ($cobradoPorPeriodo, $cobradoResumenPorPeriodo, $cobradoTransbankPorPeriodo) {
                $cobrado = min((float) $p->monto_total,
                    (float) ($cobradoPorPeriodo[$p->periodo]        ?? 0)
                  + (float) ($cobradoResumenPorPeriodo[$p->periodo] ?? 0)
                  + (float) ($cobradoTransbankPorPeriodo[$p->periodo] ?? 0));
                $pendiente = max(0, (float) $p->monto_total - $cobrado);
                return (object) [
                    'cliente_id'           => null,
                    'razon_social'         => 'Boletas ' . $p->periodo,
                    'identification'       => 'boleta_periodo_' . $p->periodo,
                    'cantidad_docs'        => (int) $p->total_boletas,
                    'cantidad_ncs'         => 0,
                    'total_facturado'      => (float) $p->monto_total,
                    'total_cobrado'        => $cobrado,
                    'total_pendiente'      => $pendiente,
                    'facturas_por_revisar' => 0,
                    'es_boleta_periodo'    => true,
                ];
            })
            ->when($soloPendientes, fn($c) => $c->filter(fn($p) => $p->total_pendiente > 0))
            ->values();
    }

    // Facturas + NCs de un cliente (o detalle de un período de boletas)
    public function facturas(Request $request, string $clienteId)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $soloPendientes = filter_var($request->get('solo_pendientes', false), FILTER_VALIDATE_BOOLEAN);

        // Caso especial: período de boletas sintético
        if (str_starts_with($clienteId, 'boleta_periodo_')) {
            $periodo = substr($clienteId, strlen('boleta_periodo_'));
            return response()->json($this->detalleBoletaPeriodo($periodo));
        }

        $facturas = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c', 'c.id', '=', 'df.cotizacion_id')
            ->leftJoin($this->efectivoCobradoSub(), 'ec.df_id', '=', 'df.id')
            ->where(function ($sq) use ($clienteId) {
                if (is_numeric($clienteId)) {
                    $sq->where('c.cliente_id', (int) $clienteId)
                       ->orWhere('df.cliente_id', (int) $clienteId);
                } else {
                    // Lookup por RUT para clientes sin registro local
                    $sq->where('df.bsale_cliente_rut', $clienteId);
                }
            })
            ->where('df.estado', 'emitido')
            ->select(
                'df.id', 'df.cotizacion_id', 'df.tipo', 'df.monto',
                DB::raw('COALESCE(df.neto, df.monto) as neto'),
                'df.fecha_emision', 'df.numero_documento_bsale',
                'df.url_pdf_bsale', 'df.id_documento_bsale', 'df.tipo_documento_bsale_id',
                'df.nc_referencia_df_id', 'df.nc_revision_estado',
                'df.monto_cobrado_manual', 'df.cobrado_manual_nota',
                DB::raw('COALESCE(ec.monto_cobrado_efectivo, 0) as monto_cobrado'),
                DB::raw('CASE WHEN df.tipo_documento_bsale_id = 2
                              THEN -(df.monto - COALESCE(ec.monto_cobrado_efectivo,0))
                              ELSE   df.monto - COALESCE(ec.monto_cobrado_efectivo,0)
                         END as pendiente'),
                DB::raw('(df.tipo_documento_bsale_id = 2) as es_nc')
            )
            ->when($desde, fn($q) => $q->where('df.fecha_emision', '>=', $desde))
            ->when($hasta, fn($q) => $q->where('df.fecha_emision', '<=', $hasta))
            ->orderByRaw('df.tipo_documento_bsale_id = 2 ASC')
            ->orderByDesc('df.fecha_emision')
            ->get();

        if ($soloPendientes) {
            $facturas = $facturas->filter(fn($f) => abs((float)$f->pendiente) > 0.01)->values();
        }

        return response()->json($facturas);
    }

    private function detalleBoletaPeriodo(string $periodo): array
    {
        $resumenes = DB::table('boleta_resumenes')
            ->where('periodo', $periodo)
            ->orderBy('forma_pago')
            ->get();

        // Cobrado por forma_pago desde boleta_resumen_movimiento (conciliación granular desde CPC)
        $cobradoResumen = DB::table('boleta_resumen_movimiento')
            ->whereIn('boleta_resumen_id', $resumenes->pluck('id'))
            ->selectRaw('boleta_resumen_id, SUM(monto) as cobrado')
            ->groupBy('boleta_resumen_id')
            ->pluck('cobrado', 'boleta_resumen_id');

        $labels = [
            'efectivo'        => 'Efectivo',
            'tarjeta_credito' => 'Tarjeta Crédito',
            'tarjeta_debito'  => 'Tarjeta Débito',
            'transferencia'   => 'Transferencia',
            'cheque'          => 'Cheque',
            'otros'           => 'Otros',
            'sin_informacion' => 'Sin Información',
        ];

        return $resumenes->map(function ($r) use ($cobradoResumen, $labels) {
            $cobradoBanco     = (float) ($cobradoResumen[$r->id] ?? 0);
            $cobradoTransbank = !empty($r->conciliado_transbank) ? (float) $r->monto_total : 0;
            $cobrado          = min((float) $r->monto_total, $cobradoBanco + $cobradoTransbank);
            $pendiente        = max(0, (float) $r->monto_total - $cobrado);
            $label            = $labels[$r->forma_pago] ?? $r->forma_pago;

            return [
                'id'                     => 'bpr_' . $r->id,
                'boleta_resumen_id'      => $r->id,
                'numero_documento_bsale' => 'BOL-' . strtoupper($r->forma_pago),
                'tipo_documento_bsale_id'=> 1,
                'tipo'                   => 'total',
                'cotizacion_id'          => null,
                'fecha_emision'          => $r->periodo . '-01',
                'monto'                  => (float) $r->monto_total,
                'neto'                   => (float) $r->monto_total,
                'monto_cobrado'          => $cobrado,
                'pendiente'              => $pendiente,
                'es_nc'                  => false,
                'es_boleta_resumen'      => true,
                'forma_pago'             => $r->forma_pago,
                'conciliado_transbank'   => !empty($r->conciliado_transbank),
                'forma_pago_label'       => $label,
                'total_boletas'          => $r->total_boletas,
                'razon_social'           => $label . ' · ' . $r->total_boletas . ' boletas',
                'url_pdf_bsale'          => null,
                'monto_cobrado_manual'   => null,
                'cobrado_manual_nota'    => null,
                'chipax_monto_por_cobrar'=> null,
                'nc_revision_estado'     => null,
            ];
        })->values()->toArray();
    }

    // Facturas de venta que requieren revision por NC
    public function porRevisar()
    {
        $facturas = DB::table('documentos_facturacion as df')
            ->where('df.nc_revision_estado', 'requiere_revision')
            ->where('df.estado', 'emitido')
            ->leftJoin('documentos_facturacion as nc', 'nc.nc_referencia_df_id', '=', 'df.id')
            ->leftJoin(
                DB::raw('(SELECT venta_id, SUM(monto) monto_cobrado FROM venta_movimiento GROUP BY venta_id) vm'),
                'vm.venta_id', '=', 'df.id'
            )
            ->leftJoin('clientes as cl', 'cl.id', '=', 'df.cliente_id')
            ->select(
                'df.id', 'df.numero_documento_bsale', 'df.tipo_documento_bsale_id',
                'df.fecha_emision', 'df.monto', 'df.url_pdf_bsale', 'df.nc_revision_estado',
                DB::raw('COALESCE(cl.razon_social, df.bsale_cliente_nombre) as cliente_nombre'),
                DB::raw('COALESCE(cl.identification, df.bsale_cliente_rut) as cliente_rut'),
                DB::raw('COALESCE(vm.monto_cobrado, 0) as monto_cobrado'),
                'nc.id as nc_id', 'nc.numero_documento_bsale as nc_numero',
                'nc.monto as nc_monto', 'nc.fecha_emision as nc_fecha'
            )
            ->orderByDesc('df.fecha_emision')
            ->get();

        $count = DB::table('documentos_facturacion')
            ->where('nc_revision_estado', 'requiere_revision')
            ->where('estado', 'emitido')
            ->count();

        return response()->json(['facturas' => $facturas, 'count' => $count]);
    }

    // PUT /api/cuentas-cobrar/{id}/cobro-manual
    public function marcarCobradoManual(Request $request, int $id)
    {
        $doc = DB::table('documentos_facturacion')->where('id', $id)->first(['id', 'monto']);
        if (!$doc) return response()->json(['error' => 'No encontrado'], 404);

        $monto = $request->input('monto', $doc->monto);
        $nota  = $request->input('nota', 'Marcado manualmente como cobrado');

        DB::table('documentos_facturacion')->where('id', $id)->update([
            'monto_cobrado_manual' => $monto,
            'cobrado_manual_nota'  => $nota,
            'updated_at'           => now(),
        ]);

        return response()->json(['ok' => true, 'monto_cobrado_manual' => $monto]);
    }

    // DELETE /api/cuentas-cobrar/{id}/cobro-manual
    public function desmarcarCobradoManual(int $id)
    {
        DB::table('documentos_facturacion')->where('id', $id)->update([
            'monto_cobrado_manual' => null,
            'cobrado_manual_nota'  => null,
            'updated_at'           => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    // GET /api/registro-ventas — lista plana de todos los documentos de venta
    public function registroVentas(Request $request)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $buscar         = $request->get('buscar');
        $monto          = $request->get('monto');
        $soloPendientes = filter_var($request->get('solo_pendientes', false), FILTER_VALIDATE_BOOLEAN);
        $tipoPago       = $request->get('tipo_pago'); // 'tarjeta' | 'efectivo' | null

        $q = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c',  'c.id',      '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_dir', 'cl_dir.id', '=', 'df.cliente_id')
            ->leftJoin('clientes as cl_cot', 'cl_cot.id', '=', 'c.cliente_id')
            ->leftJoin($this->efectivoCobradoSub(), 'ec.df_id', '=', 'df.id')
            ->where('df.estado', 'emitido')
            ->whereNotIn('df.tipo_documento_bsale_id', [1])
            ->select(
                'df.id',
                'df.numero_documento_bsale',
                'df.tipo_documento_bsale_id',
                'df.tipo',
                'df.fecha_emision',
                'df.monto',
                'df.url_pdf_bsale',
                'df.monto_cobrado_manual',
                'df.cobrado_manual_nota',
                'df.chipax_monto_por_cobrar',
                'df.pagado_con_tarjeta',
                'df.forma_pago',
                DB::raw('COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre) as razon_social'),
                DB::raw('COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut) as identification'),
                DB::raw('COALESCE(ec.monto_cobrado_efectivo, 0) as monto_cobrado'),
                DB::raw('CASE WHEN df.tipo_documento_bsale_id = 2
                              THEN -(df.monto - COALESCE(ec.monto_cobrado_efectivo,0))
                              ELSE   df.monto - COALESCE(ec.monto_cobrado_efectivo,0)
                         END as pendiente'),
                DB::raw('(df.tipo_documento_bsale_id = 2) as es_nc')
            )
            ->orderByDesc('df.fecha_emision')
            ->orderByDesc('df.id');

        if ($desde)     $q->where('df.fecha_emision', '>=', $desde);
        if ($hasta)     $q->where('df.fecha_emision', '<=', $hasta);
        if ($tipoPago === 'tarjeta')  $q->where('df.pagado_con_tarjeta', 1);
        if ($tipoPago === 'efectivo') $q->where(fn($sq) => $sq->where('df.pagado_con_tarjeta', 0)->orWhereNull('df.pagado_con_tarjeta'));
        if ($buscar) {
            $q->where(function ($sq) use ($buscar) {
                $sq->where('cl_dir.razon_social',    'like', "%$buscar%")
                   ->orWhere('cl_cot.razon_social',   'like', "%$buscar%")
                   ->orWhere('df.bsale_cliente_nombre','like', "%$buscar%")
                   ->orWhere('df.numero_documento_bsale','like', "%$buscar%")
                   ->orWhere('cl_dir.identification', 'like', "%$buscar%")
                   ->orWhere('cl_cot.identification', 'like', "%$buscar%")
                   ->orWhere('df.bsale_cliente_rut',  'like', "%$buscar%");
            });
        }
        if ($monto) {
            $montoNum = (int) preg_replace('/[^0-9]/', '', $monto);
            if ($montoNum > 0) $q->where('df.monto', $montoNum);
        }

        $documentos = $q->get();

        if ($soloPendientes) {
            $documentos = $documentos->filter(fn($d) => abs((float)$d->pendiente) > 0.01)->values();
        }

        // Filas sintéticas de boletas por forma_pago (una fila por resumen)
        $cobradoResumen = DB::table('boleta_resumen_movimiento')
            ->selectRaw('boleta_resumen_id, SUM(monto) as cobrado')
            ->groupBy('boleta_resumen_id')
            ->pluck('cobrado', 'boleta_resumen_id');

        $labels = [
            'efectivo'        => 'Efectivo',
            'tarjeta_credito' => 'Tarjeta Crédito',
            'tarjeta_debito'  => 'Tarjeta Débito',
            'transferencia'   => 'Transferencia',
            'cheque'          => 'Cheque',
            'otros'           => 'Otros',
            'sin_informacion' => 'Sin Información',
        ];

        $boletaRows = DB::table('boleta_resumenes')
            ->when($desde, fn($q) => $q->whereRaw('periodo >= ?', [substr($desde, 0, 7)]))
            ->when($hasta,  fn($q) => $q->whereRaw('periodo <= ?', [substr($hasta, 0, 7)]))
            ->orderByDesc('periodo')
            ->orderBy('forma_pago')
            ->get()
            ->map(function ($r) use ($cobradoResumen, $labels) {
                $cobradoBanco     = (float) ($cobradoResumen[$r->id] ?? 0);
                $cobradoTransbank = !empty($r->conciliado_transbank) ? (float) $r->monto_total : 0;
                $cobrado          = min((float) $r->monto_total, $cobradoBanco + $cobradoTransbank);
                $pendiente        = max(0, (float) $r->monto_total - $cobrado);
                $label            = $labels[$r->forma_pago] ?? $r->forma_pago;
                return (object) [
                    'id'                      => 'bpr_' . $r->id,
                    'boleta_resumen_id'       => $r->id,
                    'numero_documento_bsale'  => 'BOL-' . strtoupper($r->forma_pago),
                    'tipo_documento_bsale_id' => 1,
                    'tipo'                    => 'total',
                    'fecha_emision'           => $r->periodo . '-01',
                    'monto'                   => (float) $r->monto_total,
                    'razon_social'            => 'Boletas ' . $r->periodo,
                    'identification'          => $label,
                    'monto_cobrado'           => $cobrado,
                    'pendiente'               => $pendiente,
                    'es_nc'                   => false,
                    'es_boleta_resumen'       => true,
                    'forma_pago'              => $r->forma_pago,
                    'conciliado_transbank'    => !empty($r->conciliado_transbank),
                    'total_boletas'           => (int) $r->total_boletas,
                    'url_pdf_bsale'           => null,
                    'monto_cobrado_manual'    => null,
                    'cobrado_manual_nota'     => null,
                    'chipax_monto_por_cobrar' => null,
                ];
            });

        if ($soloPendientes) {
            $boletaRows = $boletaRows->filter(fn($r) => $r->pendiente > 0.01)->values();
        }

        // Si hay búsqueda de texto no incluir boletas sintéticas (no coinciden con clientes)
        if ($buscar) {
            $boletaRows = collect();
        }

        $todos = $documentos->concat($boletaRows)->sortByDesc('fecha_emision')->values();

        $totales = [
            'total_docs'      => $todos->count(),
            'total_monto'     => $todos->sum(fn($d) => isset($d->es_nc) && $d->es_nc ? -(float)$d->monto : (float)$d->monto),
            'total_pendiente' => $todos->sum(fn($d) => (float)$d->pendiente),
        ];

        return response()->json(['documentos' => $todos, 'totales' => $totales]);
    }
}
