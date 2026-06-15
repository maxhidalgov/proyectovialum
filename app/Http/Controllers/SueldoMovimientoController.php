<?php

namespace App\Http\Controllers;

use App\Models\MovimientoBancario;
use App\Models\PagoEmpleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SueldoMovimientoController extends Controller
{
    // ── Sueldos ya vinculados a un movimiento ─────────────────────────────────

    public function indexPorMovimiento(int $movimientoId)
    {
        MovimientoBancario::findOrFail($movimientoId);

        $asignados = DB::table('pagos_empleado')
            ->join('empleados', 'empleados.id', '=', 'pagos_empleado.empleado_id')
            ->where('pagos_empleado.movimiento_id', $movimientoId)
            ->select(
                'pagos_empleado.id as pago_id',
                'pagos_empleado.monto',
                'pagos_empleado.periodo',
                'pagos_empleado.tipo',
                'pagos_empleado.notas as nota',
                'empleados.nombre as empleado_nombre',
                'empleados.rut as empleado_rut',
            )
            ->get();

        return response()->json(['asignados' => $asignados]);
    }

    // ── Sueldos disponibles para vincular ─────────────────────────────────────

    public function disponiblesPorMovimiento(Request $request, int $movimientoId)
    {
        $mov    = MovimientoBancario::findOrFail($movimientoId);
        $buscar = $request->get('buscar');

        // Saldo libre del movimiento
        $asignadoMov = DB::table('compra_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                     + DB::table('gasto_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                     + DB::table('pagos_empleado')->where('movimiento_id', $movimientoId)->sum('monto');
        $saldoMov = max(0, $mov->monto - $asignadoMov);

        $orden     = $request->get('orden', 'monto');    // 'monto' | 'fecha'
        $direccion = $request->get('direccion', 'asc'); // 'asc' | 'desc'

        $q = DB::table('pagos_empleado')
            ->join('empleados', 'empleados.id', '=', 'pagos_empleado.empleado_id')
            ->whereNull('pagos_empleado.movimiento_id')   // sin vincular
            ->when($buscar, fn($q) => $q->where(function ($sq) use ($buscar) {
                $sq->where('empleados.nombre', 'like', "%$buscar%")
                   ->orWhere('empleados.rut', 'like', "%$buscar%");
            }))
            ->select(
                'pagos_empleado.id as pago_id',
                'pagos_empleado.monto',
                'pagos_empleado.periodo',
                'pagos_empleado.tipo',
                'pagos_empleado.pagado',
                'empleados.nombre as empleado_nombre',
                'empleados.rut as empleado_rut',
            );

        if ($orden === 'fecha') {
            $dir = $direccion === 'desc' ? 'DESC' : 'ASC';
            $q->orderByRaw("STR_TO_DATE(CONCAT(pagos_empleado.periodo, '-01'), '%Y-%m-%d') $dir")
              ->orderByRaw('ABS(pagos_empleado.monto - ?) ASC', [$saldoMov]);
        } else {
            $q->orderByRaw('ABS(pagos_empleado.monto - ?) ASC', [$saldoMov]);
        }

        return response()->json($q->paginate(30));
    }

    // ── Vincular sueldo a movimiento ──────────────────────────────────────────

    public function storePorMovimiento(Request $request, int $movimientoId)
    {
        $mov = MovimientoBancario::findOrFail($movimientoId);

        $request->validate([
            'pago_id' => 'required|exists:pagos_empleado,id',
        ]);

        $pago = PagoEmpleado::findOrFail($request->pago_id);

        if ($pago->movimiento_id) {
            return response()->json(['error' => 'Este pago ya está vinculado a otro movimiento'], 422);
        }

        $pago->update([
            'movimiento_id' => $movimientoId,
            'pagado'        => true,
            'fecha_pago'    => $mov->fecha_contable,
            'notas'         => $request->nota ?: $pago->notas,
        ]);

        // Marcar movimiento como conciliado si no queda saldo libre
        $totalAsignado = DB::table('compra_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                       + DB::table('gasto_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                       + DB::table('pagos_empleado')->where('movimiento_id', $movimientoId)->sum('monto');

        if ($totalAsignado >= $mov->monto) {
            $mov->update(['conciliado' => true]);
        }

        return response()->json(['pago_id' => $pago->id], 201);
    }

    // ── Desvincular sueldo de movimiento ─────────────────────────────────────

    public function destroyPorMovimiento(int $movimientoId, int $pagoId)
    {
        $pago = PagoEmpleado::where('id', $pagoId)
            ->where('movimiento_id', $movimientoId)
            ->firstOrFail();

        $pago->update([
            'movimiento_id' => null,
            'pagado'        => false,
            'fecha_pago'    => null,
        ]);

        // Desmarcar conciliado si queda saldo libre
        $mov = MovimientoBancario::find($movimientoId);
        if ($mov) {
            $totalAsignado = DB::table('compra_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                           + DB::table('gasto_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                           + DB::table('pagos_empleado')->where('movimiento_id', $movimientoId)->sum('monto');

            if ($totalAsignado < $mov->monto) {
                $mov->update(['conciliado' => false]);
            }
        }

        return response()->json(null, 204);
    }
}
