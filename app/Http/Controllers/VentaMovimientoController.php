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

        $totalCobrado  = $asignados->sum('monto_asignado');
        $saldoPorCobrar = max(0, $venta->monto - $totalCobrado);

        return response()->json([
            'asignados'      => $asignados,
            'saldo_por_cobrar' => $saldoPorCobrar,
        ]);
    }

    // ── Movimientos crédito disponibles (ordenados por monto más cercano) ─────

    public function disponibles(Request $request, int $ventaId)
    {
        $venta   = DB::table('documentos_facturacion')->where('id', $ventaId)->firstOrFail();
        $cobrado = DB::table('venta_movimiento')->where('venta_id', $ventaId)->sum('monto');
        $saldo   = max(0, $venta->monto - $cobrado);
        $buscar  = $request->get('buscar');

        $movs = DB::table('movimientos_bancarios as m')
            ->leftJoin(
                DB::raw('(SELECT movimiento_id, SUM(monto) as asignado FROM venta_movimiento GROUP BY movimiento_id) as vm'),
                'm.id', '=', 'vm.movimiento_id'
            )
            ->where('m.tipo', 'C')
            ->whereRaw('m.monto - COALESCE(vm.asignado, 0) > 0')
            ->select(
                'm.id',
                'm.fecha_contable',
                'm.descripcion',
                'm.glosa',
                'm.monto',
                DB::raw('m.monto - COALESCE(vm.asignado, 0) as saldo_por_asignar')
            )
            ->when($buscar, fn($q) => $q->where('m.descripcion', 'like', "%$buscar%"))
            ->orderByRaw('ABS(m.monto - COALESCE(vm.asignado, 0) - ?) ASC', [$saldo])
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
            ->where('vm.movimiento_id', $movimientoId)
            ->select(
                'vm.id as pivot_id',
                'vm.monto as monto_asignado',
                'df.id as venta_id',
                'df.folio',
                'df.fecha_emision',
                'df.nombre_receptor',
                'df.monto',
                'df.tipo_doc',
            )
            ->get();

        return response()->json(['asignados' => $asignados]);
    }

    // ── Ventas disponibles para asignar a un movimiento crédito ──────────────
    public function disponiblesPorMovimiento(Request $request, int $movimientoId)
    {
        $buscar = $request->get('buscar');

        $ventas = DB::table('documentos_facturacion as df')
            ->leftJoin(
                DB::raw('(SELECT venta_id, SUM(monto) as cobrado FROM venta_movimiento GROUP BY venta_id) as vm'),
                'df.id', '=', 'vm.venta_id'
            )
            ->where('df.estado', 'emitido')
            ->whereRaw('df.monto - COALESCE(vm.cobrado, 0) > 0')
            ->select(
                'df.id',
                'df.folio',
                'df.fecha_emision',
                'df.nombre_receptor',
                'df.rut_receptor',
                'df.monto',
                'df.tipo_doc',
                DB::raw('df.monto - COALESCE(vm.cobrado, 0) as saldo_por_cobrar')
            )
            ->when($buscar, fn($q) => $q->where(function ($q2) use ($buscar) {
                $q2->where('df.folio', 'like', "%$buscar%")
                   ->orWhere('df.nombre_receptor', 'like', "%$buscar%");
            }))
            ->orderByDesc('df.fecha_emision')
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
            ['monto' => $request->monto, 'updated_at' => now(), 'created_at' => now()]
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
