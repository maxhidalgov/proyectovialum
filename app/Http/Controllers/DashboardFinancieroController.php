<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardFinancieroController extends Controller
{
    public function index()
    {
        $hoy        = Carbon::now();
        $hace12m    = $hoy->copy()->subMonths(11)->startOfMonth();
        $hace4m     = $hoy->copy()->subMonths(3)->startOfMonth();

        // ── KPIs ──────────────────────────────────────────────────────────────

        $porCobrar = DB::table('documentos_facturacion as df')
            ->leftJoin(
                DB::raw('(SELECT venta_id, SUM(monto) as cobrado FROM venta_movimiento GROUP BY venta_id) as vm'),
                'vm.venta_id', '=', 'df.id'
            )
            ->where('df.estado', 'emitido')
            ->whereRaw("LOWER(COALESCE(df.bsale_cliente_nombre, '')) NOT LIKE '%consumidor%'")
            ->selectRaw('COALESCE(SUM(df.monto - COALESCE(vm.cobrado, 0)), 0) as pendiente')
            ->value('pendiente');

        // Misma lógica que CuentasPorPagarController::efectivoPagadoSub()
        $efectivoPagadoSub = DB::raw("(
            SELECT
                c.id AS compra_id,
                COALESCE(pb.monto_banco, 0)
                  + CASE WHEN c.tipo_dte IN (61)
                         THEN COALESCE(ncnc.monto_nc, 0)
                         ELSE COALESCE(ncf.monto_nc, 0) + COALESCE(ncref.monto_nc, 0)
                    END AS monto_pagado_efectivo
            FROM compras c
            LEFT JOIN (SELECT compra_id, SUM(monto) monto_banco FROM compra_movimiento GROUP BY compra_id) pb
                   ON pb.compra_id = c.id
            LEFT JOIN (SELECT factura_id AS compra_id, SUM(monto) monto_nc FROM compra_nc_aplicacion GROUP BY factura_id) ncf
                   ON ncf.compra_id = c.id
            LEFT JOIN (SELECT nc_id AS compra_id, SUM(monto) monto_nc FROM compra_nc_aplicacion GROUP BY nc_id) ncnc
                   ON ncnc.compra_id = c.id
            LEFT JOIN (
                SELECT nc.nc_referencia_id AS compra_id, SUM(nc.total) AS monto_nc
                FROM compras nc
                WHERE nc.tipo_dte IN (61)
                  AND nc.nc_referencia_id IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM compra_nc_aplicacion ap WHERE ap.nc_id = nc.id)
                GROUP BY nc.nc_referencia_id
            ) ncref ON ncref.compra_id = c.id
        ) AS ef");

        $porPagar = DB::table('compras')
            ->leftJoin($efectivoPagadoSub, 'ef.compra_id', '=', 'compras.id')
            ->where('compras.pagado_historico', false)
            ->selectRaw("COALESCE(SUM(
                CASE WHEN compras.tipo_dte IN (61)
                     THEN -(compras.total - COALESCE(ef.monto_pagado_efectivo,0))
                     ELSE   compras.total - COALESCE(ef.monto_pagado_efectivo,0)
                END
            ), 0) as pendiente")
            ->value('pendiente');

        $ultimoMov = DB::table('movimientos_bancarios')
            ->whereNotNull('saldo_disponible')
            ->orderByDesc('fecha_contable')
            ->orderByRaw("COALESCE(fecha_hora_mov, '1900-01-01 00:00:00') DESC")
            ->orderByDesc('id')
            ->select('saldo_disponible', 'fecha_contable')
            ->first();
        $saldoCta      = $ultimoMov ? (float) $ultimoMov->saldo_disponible : null;
        $saldoCtaFecha = $ultimoMov ? $ultimoMov->fecha_contable : null;

        $promedioDias = DB::table('documentos_facturacion as df')
            ->join('venta_movimiento as vm', 'vm.venta_id', '=', 'df.id')
            ->join('movimientos_bancarios as mb', 'mb.id', '=', 'vm.movimiento_id')
            ->whereNotNull('df.fecha_emision')
            ->selectRaw('AVG(DATEDIFF(mb.fecha_contable, df.fecha_emision)) as promedio')
            ->value('promedio');

        // ── Top 5 clientes por cobrar ─────────────────────────────────────────

        $topClientes = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c',     'c.id',      '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_cot',    'cl_cot.id', '=', 'c.cliente_id')
            ->leftJoin('clientes as cl_dir',    'cl_dir.id', '=', 'df.cliente_id')
            ->leftJoin(
                DB::raw('(SELECT venta_id, SUM(monto) as cobrado FROM venta_movimiento GROUP BY venta_id) as vm'),
                'vm.venta_id', '=', 'df.id'
            )
            ->where('df.estado', 'emitido')
            ->whereRaw("LOWER(COALESCE(df.bsale_cliente_nombre, '')) NOT LIKE '%consumidor%'")
            ->select(
                DB::raw("COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre, 'Sin nombre') as nombre"),
                DB::raw("COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut) as rut"),
                DB::raw('COUNT(df.id) as documentos'),
                DB::raw('SUM(df.monto) - COALESCE(SUM(vm.cobrado), 0) as pendiente')
            )
            ->groupBy(
                DB::raw("COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre, 'Sin nombre')"),
                DB::raw("COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut)")
            )
            ->havingRaw('pendiente > 0')
            ->orderByDesc('pendiente')
            ->limit(5)
            ->get();

        // ── Flujo de Caja — últimos 12 meses (movimientos bancarios reales) ───

        $flujoCaja = DB::table('movimientos_bancarios')
            ->where('fecha_contable', '>=', $hace12m->toDateString())
            ->selectRaw("
                DATE_FORMAT(fecha_contable, '%Y-%m') as mes,
                SUM(CASE WHEN tipo = 'C' THEN monto ELSE 0 END) as ingresos,
                SUM(CASE WHEN tipo = 'D' THEN monto ELSE 0 END) as egresos
            ")
            ->groupBy(DB::raw("DATE_FORMAT(fecha_contable, '%Y-%m')"))
            ->orderBy('mes')
            ->get();

        // ── Resultado Operacional — últimos 4 meses ───────────────────────────

        $ingresosMes = DB::table('documentos_facturacion')
            ->where('estado', 'emitido')
            ->where('fecha_emision', '>=', $hace4m->toDateString())
            ->whereNotNull('neto')
            ->selectRaw("DATE_FORMAT(fecha_emision, '%Y-%m') as mes, SUM(neto) as total")
            ->groupBy(DB::raw("DATE_FORMAT(fecha_emision, '%Y-%m')"))
            ->pluck('total', 'mes');

        $comprasMes = DB::table('compras')
            ->where('pagado_historico', false)
            ->where('fecha_emision', '>=', $hace4m->toDateString())
            ->selectRaw("DATE_FORMAT(fecha_emision, '%Y-%m') as mes, SUM(neto) as total")
            ->groupBy(DB::raw("DATE_FORMAT(fecha_emision, '%Y-%m')"))
            ->pluck('total', 'mes');

        $gastosMes = DB::table('gastos')
            ->where('fecha', '>=', $hace4m->toDateString())
            ->where(function ($q) {
                $q->whereNull('chipax_tipo')
                  ->orWhereNotIn('chipax_tipo', ['impuesto', 'previred']);
            })
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy(DB::raw("DATE_FORMAT(fecha, '%Y-%m')"))
            ->pluck('total', 'mes');

        $remuMes = DB::table('pagos_empleado')
            ->where('periodo', '>=', $hace4m->toDateString())
            ->selectRaw("DATE_FORMAT(periodo, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy(DB::raw("DATE_FORMAT(periodo, '%Y-%m')"))
            ->pluck('total', 'mes');

        $previredMes = DB::table('gastos')
            ->where('chipax_tipo', 'previred')
            ->where('fecha', '>=', $hace4m->toDateString())
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy(DB::raw("DATE_FORMAT(fecha, '%Y-%m')"))
            ->pluck('total', 'mes');

        $resultadoOperacional = [];
        $cursor = $hace4m->copy();
        while ($cursor <= $hoy) {
            $m   = $cursor->format('Y-m');
            $ing = (float) ($ingresosMes[$m] ?? 0);
            $cmp = (float) ($comprasMes[$m]  ?? 0);
            $gst = (float) ($gastosMes[$m]   ?? 0);
            $rem = (float) ($remuMes[$m]     ?? 0) + (float) ($previredMes[$m] ?? 0);

            $resultadoOperacional[] = [
                'mes'             => $m,
                'ingresos'        => $ing,
                'compras'         => $cmp,
                'gastos'          => $gst,
                'remuneraciones'  => $rem,
                'resultado'       => $ing - $cmp - $gst - $rem,
            ];
            $cursor->addMonth();
        }

        return response()->json([
            'kpis' => [
                'por_cobrar'          => (float) ($porCobrar ?? 0),
                'por_pagar'           => (float) ($porPagar  ?? 0),
                'saldo_cta_corriente'       => (float) ($saldoCta      ?? 0),
                'saldo_cta_corriente_fecha' => $saldoCtaFecha,
                'promedio_dias_cobro' => (int)   round((float) ($promedioDias ?? 0)),
            ],
            'top_clientes'          => $topClientes,
            'flujo_caja'            => $flujoCaja,
            'resultado_operacional' => $resultadoOperacional,
        ]);
    }
}
