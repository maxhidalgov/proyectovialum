<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaMovimientoController extends Controller
{
    // ── Movimientos asignados a una factura de venta ──────────────────────────

    public function index(int $ventaId)
    {
        $venta = DB::table('documentos_facturacion')->where('id', $ventaId)->firstOrFail();

        $asignados = DB::table('venta_movimiento as vm')
            ->join('movimientos_bancarios as m', 'm.id', '=', 'vm.movimiento_id')
            ->where('vm.venta_id', $ventaId)
            ->select(
                'vm.id as pivot_id',
                'vm.monto as monto_asignado',
                'm.id as movimiento_id',
                'm.fecha_contable',
                'm.descripcion',
                'm.monto as monto_movimiento',
            )
            ->get();

        $totalCobrado = $asignados->sum('monto_asignado');

        $totalTransbank = (float) DB::table('transbank_factura as tvf')
            ->join('transbank_transacciones as tt', 'tt.id', '=', 'tvf.transaccion_id')
            ->where('tvf.documento_id', $ventaId)
            ->sum('tt.monto_original');

        $totalManual = (float) ($venta->monto_cobrado_manual ?? 0);

        // NC aplicadas explícitamente (venta_nc_aplicacion)
        $totalNC = (float) DB::table('venta_nc_aplicacion')
            ->where('factura_id', $ventaId)
            ->sum('monto');

        // NC de Bsale que referencian esta factura (nc_referencia_df_id), sin doble conteo
        $totalNCRef = (float) DB::table('documentos_facturacion as nc')
            ->where('nc.tipo_documento_bsale_id', 2)
            ->where('nc.nc_referencia_df_id', $ventaId)
            ->whereNotExists(fn($q) => $q->from('venta_nc_aplicacion')->whereColumn('venta_nc_aplicacion.nc_id', 'nc.id'))
            ->sum('nc.monto');

        $saldoPorCobrar = max(0, $venta->monto - $totalCobrado - $totalTransbank - $totalManual - $totalNC - $totalNCRef);

        return response()->json([
            'asignados'         => $asignados,
            'cobrado_transbank' => $totalTransbank,
            'cobrado_manual'    => $totalManual,
            'cobrado_nc'        => $totalNC + $totalNCRef,
            'saldo_por_cobrar'  => $saldoPorCobrar,
        ]);
    }

    // ── Movimientos crédito disponibles (ordenados por monto más cercano) ─────

    public function disponibles(Request $request, int $ventaId)
    {
        $venta   = DB::table('documentos_facturacion')->where('id', $ventaId)->firstOrFail();
        $cobrado = DB::table('venta_movimiento')->where('venta_id', $ventaId)->sum('monto');
        $saldo   = max(0, $venta->monto - $cobrado);
        $buscar  = $request->get('buscar');
        $monto   = $request->get('monto');

        $movs = DB::table('movimientos_bancarios as m')
            ->leftJoin(
                DB::raw('(SELECT movimiento_id, SUM(monto) as asignado FROM venta_movimiento GROUP BY movimiento_id) as vm'),
                'm.id', '=', 'vm.movimiento_id'
            )
            ->leftJoin(
                DB::raw('(SELECT movimiento_id, SUM(monto) as asignado FROM boleta_resumen_movimiento GROUP BY movimiento_id) as brm'),
                'm.id', '=', 'brm.movimiento_id'
            )
            ->where('m.tipo', 'C')
            ->where('m.conciliado', 0)
            ->whereRaw('m.monto - COALESCE(vm.asignado, 0) - COALESCE(brm.asignado, 0) > 0')
            ->select(
                'm.id',
                'm.fecha_contable',
                'm.descripcion',
                'm.glosa',
                'm.monto',
                DB::raw('m.monto - COALESCE(vm.asignado, 0) - COALESCE(brm.asignado, 0) as saldo_por_asignar')
            )
            ->when($buscar, fn($q) => $q->where('m.descripcion', 'like', "%$buscar%"))
            ->when($monto, function ($q) use ($monto) {
                $montoNum = (int) preg_replace('/[^0-9]/', '', $monto);
                if ($montoNum > 0) $q->where('m.monto', $montoNum);
            })
            ->orderByRaw('ABS(m.monto - COALESCE(vm.asignado, 0) - COALESCE(brm.asignado, 0) - ?) ASC', [$saldo])
            ->paginate(30);

        return response()->json($movs);
    }

    // ── Asignar movimiento a factura ──────────────────────────────────────────

    public function store(Request $request, int $ventaId)
    {
        $request->validate([
            'movimiento_id' => 'required|exists:movimientos_bancarios,id',
            'monto'         => 'required|numeric|min:0.01',
        ]);

        DB::table('venta_movimiento')->updateOrInsert(
            ['venta_id' => $ventaId, 'movimiento_id' => $request->movimiento_id],
            ['monto' => $request->monto, 'updated_at' => now(), 'created_at' => now()]
        );

        // Marcar movimiento conciliado si queda sin saldo
        $totalAsignadoMov = DB::table('venta_movimiento')
            ->where('movimiento_id', $request->movimiento_id)
            ->sum('monto');
        $monto = DB::table('movimientos_bancarios')->where('id', $request->movimiento_id)->value('monto');
        if ($totalAsignadoMov >= $monto) {
            DB::table('movimientos_bancarios')
                ->where('id', $request->movimiento_id)
                ->update(['conciliado' => true]);
        }

        return response()->json(['ok' => true], 201);
    }

    // ── Desasignar movimiento ─────────────────────────────────────────────────

    public function destroy(int $ventaId, int $pivotId)
    {
        $pivot = DB::table('venta_movimiento')
            ->where('id', $pivotId)
            ->where('venta_id', $ventaId)
            ->firstOrFail();

        $movId = $pivot->movimiento_id;
        DB::table('venta_movimiento')->where('id', $pivotId)->delete();

        // Si el movimiento ya no está completamente cubierto, desmarcar conciliado
        $totalAsignado = DB::table('venta_movimiento')->where('movimiento_id', $movId)->sum('monto');
        $monto = DB::table('movimientos_bancarios')->where('id', $movId)->value('monto');
        if ($totalAsignado < $monto) {
            DB::table('movimientos_bancarios')
                ->where('id', $movId)
                ->update(['conciliado' => false]);
        }

        return response()->json(null, 204);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Perspectiva desde el MOVIMIENTO (para conciliar ingresos ↔ ventas)
    // ══════════════════════════════════════════════════════════════════════════

    // ── Ventas asignadas a un movimiento crédito ──────────────────────────────
    public function indexPorMovimiento(int $movimientoId)
    {
        $asignados = DB::table('venta_movimiento as vm')
            ->join('documentos_facturacion as df', 'df.id', '=', 'vm.venta_id')
            ->leftJoin('cotizaciones as c',    'c.id',    '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_cot',   'cl_cot.id', '=', 'c.cliente_id')
            ->leftJoin('clientes as cl_dir',   'cl_dir.id', '=', 'df.cliente_id')
            ->where('vm.movimiento_id', $movimientoId)
            ->whereNotIn('df.tipo_documento_bsale_id', [1])
            ->select(
                'vm.id as pivot_id',
                'vm.monto as monto_asignado',
                'vm.nota',
                'df.id as venta_id',
                DB::raw('df.numero_documento_bsale as folio'),
                'df.fecha_emision',
                DB::raw('COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre) as nombre_receptor'),
                'df.monto',
                DB::raw('df.tipo as tipo_doc'),
            )
            ->get();

        return response()->json(['asignados' => $asignados]);
    }

    // ── Ventas disponibles para asignar a un movimiento crédito ──────────────
    public function disponiblesPorMovimiento(Request $request, int $movimientoId)
    {
        $buscar = $request->get('buscar');

        // Saldo restante del movimiento (para ordenar por cercanía de monto)
        $mov = DB::table('movimientos_bancarios')->where('id', $movimientoId)->firstOrFail();
        $asignadoMov = DB::table('venta_movimiento')
            ->where('movimiento_id', $movimientoId)
            ->sum('monto');
        $saldoMov = max(0, $mov->monto - $asignadoMov);

        $ventas = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c',    'c.id',    '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_cot',   'cl_cot.id', '=', 'c.cliente_id')
            ->leftJoin('clientes as cl_dir',   'cl_dir.id', '=', 'df.cliente_id')
            ->leftJoin(
                DB::raw('(SELECT venta_id, SUM(monto) as cobrado FROM venta_movimiento GROUP BY venta_id) as vm'),
                'df.id', '=', 'vm.venta_id'
            )
            ->leftJoin(
                DB::raw('(SELECT tvf.documento_id, SUM(tt.monto_original) as cobrado_transbank
                          FROM transbank_factura tvf
                          JOIN transbank_transacciones tt ON tt.id = tvf.transaccion_id
                          GROUP BY tvf.documento_id) as tb'),
                'df.id', '=', 'tb.documento_id'
            )
            // NC aplicadas manualmente (venta_nc_aplicacion)
            ->leftJoin(
                DB::raw('(SELECT factura_id, SUM(monto) as cobrado_nc FROM venta_nc_aplicacion GROUP BY factura_id) as vnca'),
                'df.id', '=', 'vnca.factura_id'
            )
            // NC de Bsale que referencian esta factura (sin doble conteo con vnca)
            ->leftJoin(
                DB::raw('(SELECT nc_ref.nc_referencia_df_id AS doc_id, SUM(nc_ref.monto) AS cobrado_nc_ref
                          FROM documentos_facturacion nc_ref
                          WHERE nc_ref.tipo_documento_bsale_id = 2
                            AND nc_ref.nc_referencia_df_id IS NOT NULL
                            AND NOT EXISTS (SELECT 1 FROM venta_nc_aplicacion vnca2 WHERE vnca2.nc_id = nc_ref.id)
                          GROUP BY nc_ref.nc_referencia_df_id) as ncref'),
                'df.id', '=', 'ncref.doc_id'
            )
            ->whereNotExists(function ($q) use ($movimientoId) {
                $q->from('venta_movimiento as vm2')
                  ->whereColumn('vm2.venta_id', 'df.id')
                  ->where('vm2.movimiento_id', $movimientoId);
            })
            ->where('df.estado', 'emitido')
            ->whereNotIn('df.tipo_documento_bsale_id', [1, 2])
            ->whereRaw('df.monto - COALESCE(vm.cobrado,0) - COALESCE(tb.cobrado_transbank,0) - COALESCE(vnca.cobrado_nc,0) - COALESCE(ncref.cobrado_nc_ref,0) > 0')
            ->select(
                'df.id',
                DB::raw('df.numero_documento_bsale as folio'),
                'df.fecha_emision',
                DB::raw('COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre) as nombre_receptor'),
                DB::raw('COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut) as rut_receptor'),
                'df.monto',
                DB::raw('df.tipo as tipo_doc'),
                DB::raw('df.monto - COALESCE(vm.cobrado,0) - COALESCE(tb.cobrado_transbank,0) - COALESCE(vnca.cobrado_nc,0) - COALESCE(ncref.cobrado_nc_ref,0) as saldo_por_cobrar')
            )
            ->when($buscar, fn($q) => $q->where(function ($q2) use ($buscar) {
                $q2->where('df.numero_documento_bsale', 'like', "%$buscar%")
                   ->orWhere('df.bsale_cliente_nombre',  'like', "%$buscar%")
                   ->orWhere('cl_cot.razon_social',       'like', "%$buscar%")
                   ->orWhere('cl_dir.razon_social',       'like', "%$buscar%");
            }))
            ->orderByRaw('ABS(df.monto - COALESCE(vm.cobrado,0) - COALESCE(tb.cobrado_transbank,0) - COALESCE(vnca.cobrado_nc,0) - COALESCE(ncref.cobrado_nc_ref,0) - ?) ASC', [$saldoMov])
            ->paginate(30);

        return response()->json($ventas);
    }

    // ── Asignar venta a movimiento crédito ────────────────────────────────────
    public function storePorMovimiento(Request $request, int $movimientoId)
    {
        $request->validate([
            'venta_id' => 'required|exists:documentos_facturacion,id',
            'monto'    => 'required|numeric|min:0.01',
        ]);

        DB::table('venta_movimiento')->updateOrInsert(
            ['venta_id' => $request->venta_id, 'movimiento_id' => $movimientoId],
            ['monto' => $request->monto, 'nota' => $request->nota ?: null, 'updated_at' => now(), 'created_at' => now()]
        );

        // Marcar conciliado si el movimiento queda totalmente cubierto
        $totalAsignado = DB::table('venta_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
        $monto = DB::table('movimientos_bancarios')->where('id', $movimientoId)->value('monto');
        if ($totalAsignado >= $monto) {
            DB::table('movimientos_bancarios')->where('id', $movimientoId)->update(['conciliado' => true]);
        }

        return response()->json(['ok' => true], 201);
    }

    // ── Desasignar venta de movimiento crédito ────────────────────────────────
    public function destroyPorMovimiento(int $movimientoId, int $pivotId)
    {
        $pivot = DB::table('venta_movimiento')
            ->where('id', $pivotId)
            ->where('movimiento_id', $movimientoId)
            ->firstOrFail();

        DB::table('venta_movimiento')->where('id', $pivotId)->delete();

        // Desmarcar conciliado si ya no queda cubierto
        $totalAsignado = DB::table('venta_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
        $monto = DB::table('movimientos_bancarios')->where('id', $movimientoId)->value('monto');
        if ($totalAsignado < $monto) {
            DB::table('movimientos_bancarios')->where('id', $movimientoId)->update(['conciliado' => false]);
        }

        return response()->json(null, 204);
    }
}
