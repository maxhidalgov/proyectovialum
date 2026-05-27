<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\MovimientoBancario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GastoMovimientoController extends Controller
{
    // ══ Perspectiva desde gasto ═══════════════════════════════════════════════

    public function index(int $gastoId)
    {
        $gasto = Gasto::findOrFail($gastoId);

        $asignados = DB::table('gasto_movimiento')
            ->join('movimientos_bancarios', 'movimientos_bancarios.id', '=', 'gasto_movimiento.movimiento_id')
            ->where('gasto_movimiento.gasto_id', $gastoId)
            ->select(
                'gasto_movimiento.id as pivot_id',
                'gasto_movimiento.monto as monto_asignado',
                'movimientos_bancarios.id',
                'movimientos_bancarios.fecha_contable',
                'movimientos_bancarios.descripcion',
                'movimientos_bancarios.monto',
                'movimientos_bancarios.glosa',
            )
            ->get();

        $saldo_por_conciliar = max(0, $gasto->monto - $asignados->sum('monto_asignado'));

        return response()->json([
            'gasto'              => $gasto,
            'asignados'          => $asignados,
            'saldo_por_conciliar' => $saldo_por_conciliar,
        ]);
    }

    public function disponibles(Request $request, int $gastoId)
    {
        $gasto  = Gasto::findOrFail($gastoId);
        $buscar = $request->get('buscar');

        $asignadoGasto = DB::table('gasto_movimiento')->where('gasto_id', $gastoId)->sum('monto');
        $saldoGasto    = max(0, $gasto->monto - $asignadoGasto);

        $movs = DB::table('movimientos_bancarios')
            ->leftJoin(
                DB::raw('(SELECT movimiento_id, SUM(monto) as asignado FROM gasto_movimiento GROUP BY movimiento_id) as ag'),
                'movimientos_bancarios.id', '=', 'ag.movimiento_id'
            )
            ->whereNotExists(function ($q) use ($gastoId) {
                $q->from('gasto_movimiento')
                  ->whereColumn('gasto_movimiento.movimiento_id', 'movimientos_bancarios.id')
                  ->where('gasto_movimiento.gasto_id', $gastoId);
            })
            ->where('movimientos_bancarios.tipo', 'D')
            ->when($buscar, fn($q) => $q->where(function ($sq) use ($buscar) {
                $sq->where('movimientos_bancarios.descripcion', 'like', "%$buscar%")
                   ->orWhere('movimientos_bancarios.glosa', 'like', "%$buscar%");
            }))
            ->select(
                'movimientos_bancarios.id',
                'movimientos_bancarios.fecha_contable',
                'movimientos_bancarios.descripcion',
                'movimientos_bancarios.glosa',
                'movimientos_bancarios.monto',
                DB::raw('movimientos_bancarios.monto - COALESCE(ag.asignado, 0) as saldo_por_asignar')
            )
            ->havingRaw('saldo_por_asignar > 0')
            ->orderByRaw('ABS(saldo_por_asignar - ?) ASC', [$saldoGasto])
            ->paginate(30);

        return response()->json($movs);
    }

    public function store(Request $request, int $gastoId)
    {
        $gasto = Gasto::findOrFail($gastoId);

        $request->validate([
            'movimiento_id' => 'required|exists:movimientos_bancarios,id',
            'monto'         => 'required|numeric|min:0.01',
        ]);

        if (DB::table('gasto_movimiento')
            ->where('gasto_id', $gastoId)
            ->where('movimiento_id', $request->movimiento_id)
            ->exists()) {
            return response()->json(['error' => 'Este movimiento ya está asignado a este gasto'], 422);
        }

        $mov = MovimientoBancario::findOrFail($request->movimiento_id);

        $asignadoMov  = DB::table('gasto_movimiento')->where('movimiento_id', $request->movimiento_id)->sum('monto')
                      + DB::table('compra_movimiento')->where('movimiento_id', $request->movimiento_id)->sum('monto');
        $saldoMov     = $mov->monto - $asignadoMov;

        $asignadoGasto = DB::table('gasto_movimiento')->where('gasto_id', $gastoId)->sum('monto');
        $saldoGasto    = $gasto->monto - $asignadoGasto;

        $monto = min($request->monto, $saldoMov, $saldoGasto);
        if ($monto <= 0) {
            return response()->json(['error' => 'No hay saldo disponible para asignar'], 422);
        }

        $pivot = DB::table('gasto_movimiento')->insertGetId([
            'gasto_id'     => $gastoId,
            'movimiento_id' => $request->movimiento_id,
            'monto'         => $monto,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json(['pivot_id' => $pivot, 'monto_asignado' => $monto], 201);
    }

    public function destroy(int $gastoId, int $pivotId)
    {
        $deleted = DB::table('gasto_movimiento')
            ->where('id', $pivotId)
            ->where('gasto_id', $gastoId)
            ->delete();

        if (!$deleted) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        return response()->json(null, 204);
    }

    // ══ Perspectiva desde movimiento ══════════════════════════════════════════

    public function indexPorMovimiento(int $movimientoId)
    {
        MovimientoBancario::findOrFail($movimientoId);

        $asignados = DB::table('gasto_movimiento')
            ->join('gastos', 'gastos.id', '=', 'gasto_movimiento.gasto_id')
            ->where('gasto_movimiento.movimiento_id', $movimientoId)
            ->select(
                'gasto_movimiento.id as pivot_id',
                'gasto_movimiento.monto as monto_asignado',
                'gastos.id',
                'gastos.fecha',
                'gastos.descripcion',
                'gastos.categoria',
                'gastos.monto',
                'gastos.proveedor',
            )
            ->get();

        return response()->json(['asignados' => $asignados]);
    }

    public function disponiblesPorMovimiento(Request $request, int $movimientoId)
    {
        $mov    = MovimientoBancario::findOrFail($movimientoId);
        $buscar = $request->get('buscar');

        $asignadoMov = DB::table('gasto_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                     + DB::table('compra_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
        $saldoMov    = max(0, $mov->monto - $asignadoMov);

        $gastos = DB::table('gastos')
            ->leftJoin(
                DB::raw('(SELECT gasto_id, SUM(monto) as pagado FROM gasto_movimiento GROUP BY gasto_id) as pagos'),
                'gastos.id', '=', 'pagos.gasto_id'
            )
            ->whereNotExists(function ($q) use ($movimientoId) {
                $q->from('gasto_movimiento')
                  ->whereColumn('gasto_movimiento.gasto_id', 'gastos.id')
                  ->where('gasto_movimiento.movimiento_id', $movimientoId);
            })
            ->when($buscar, fn($q) => $q->where(function ($sq) use ($buscar) {
                $sq->where('gastos.descripcion', 'like', "%$buscar%")
                   ->orWhere('gastos.proveedor', 'like', "%$buscar%");
            }))
            ->select(
                'gastos.id',
                'gastos.fecha',
                'gastos.descripcion',
                'gastos.categoria',
                'gastos.monto',
                'gastos.proveedor',
                DB::raw('gastos.monto - COALESCE(pagos.pagado, 0) as saldo_por_conciliar')
            )
            ->havingRaw('saldo_por_conciliar > 0')
            ->orderByRaw('ABS(saldo_por_conciliar - ?) ASC', [$saldoMov])
            ->paginate(30);

        return response()->json($gastos);
    }

    public function storePorMovimiento(Request $request, int $movimientoId)
    {
        $mov = MovimientoBancario::findOrFail($movimientoId);

        $request->validate([
            'gasto_id' => 'required|exists:gastos,id',
            'monto'    => 'required|numeric|min:0.01',
        ]);

        $gasto = Gasto::findOrFail($request->gasto_id);

        if (DB::table('gasto_movimiento')
            ->where('gasto_id', $request->gasto_id)
            ->where('movimiento_id', $movimientoId)
            ->exists()) {
            return response()->json(['error' => 'Este gasto ya está asignado a este movimiento'], 422);
        }

        $asignadoMov  = DB::table('gasto_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                      + DB::table('compra_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
        $saldoMov     = $mov->monto - $asignadoMov;

        $asignadoGasto = DB::table('gasto_movimiento')->where('gasto_id', $request->gasto_id)->sum('monto');
        $saldoGasto    = $gasto->monto - $asignadoGasto;

        $monto = min($request->monto, $saldoMov, $saldoGasto);
        if ($monto <= 0) {
            return response()->json(['error' => 'No hay saldo disponible para asignar'], 422);
        }

        $pivot = DB::table('gasto_movimiento')->insertGetId([
            'gasto_id'      => $request->gasto_id,
            'movimiento_id' => $movimientoId,
            'monto'         => $monto,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Marcar movimiento como conciliado si ya no tiene saldo libre
        $totalAsignado = $asignadoMov + $monto;
        if ($totalAsignado >= $mov->monto) {
            $mov->update(['conciliado' => true]);
        }

        return response()->json([
            'pivot_id'       => $pivot,
            'monto_asignado' => $monto,
            'saldo_por_asignar' => max(0, $saldoMov - $monto),
        ], 201);
    }

    public function destroyPorMovimiento(int $movimientoId, int $pivotId)
    {
        $deleted = DB::table('gasto_movimiento')
            ->where('id', $pivotId)
            ->where('movimiento_id', $movimientoId)
            ->delete();

        if (!$deleted) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        // Desmarcar conciliado si queda saldo libre
        $mov = MovimientoBancario::find($movimientoId);
        if ($mov) {
            $asignado = DB::table('gasto_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                      + DB::table('compra_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
            if ($asignado < $mov->monto) {
                $mov->update(['conciliado' => false]);
            }
        }

        return response()->json(null, 204);
    }
}
