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
            ->selectRaw('COALESCE(SUM(df.monto) - SUM(COALESCE(vm.cobrado, 0)), 0) as pendiente')
            ->value('pendiente');

        $porPagar = DB::table('compras')
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) as pagado FROM compra_movimiento GROUP BY compra_id) as cm'),
                'cm.compra_id', '=', 'compras.id'
            )
            ->where('compras.pagado_historico', false)
            ->selectRaw('COALESCE(SUM(compras.total) - SUM(COALESCE(cm.pagado, 0)), 0) as pendiente')
            ->value('pendiente');

        $saldoCta = DB::table('movimientos_bancarios')
            ->whereNotNull('saldo_disponible')
            ->orderByDesc('fecha_contable')
            ->orderByDesc('id')
            ->value('saldo_disponible');

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
            ->selectRaw("DATE_FORMAT(periodo, '%Y-%m') as mes, SUM(monto_liquido) as total")
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
                'saldo_cta_corriente' => (float) ($saldoCta  ?? 0),
                'promedio_dias_cobro' => (int)   round((float) ($promedioDias ?? 0)),
            ],
            'top_clientes'          => $topClientes,
            'flujo_caja'            => $flujoCaja,
            'resultado_operacional' => $resultadoOperacional,
        ]);
    }
}
