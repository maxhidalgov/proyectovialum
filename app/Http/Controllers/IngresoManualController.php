<?php

namespace App\Http\Controllers;

use App\Models\IngresoManual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngresoManualController extends Controller
{
    // ── Listado general (para EERR u otras vistas) ────────────────────────────
    public function index(Request $request)
    {
        $q = IngresoManual::orderByDesc('fecha')->orderByDesc('id');

        if ($request->filled('desde')) {
            $q->where('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $q->where('fecha', '<=', $request->hasta);
        }
        if ($request->filled('categoria')) {
            $q->where('categoria', $request->categoria);
        }
        if ($request->filled('buscar')) {
            $q->where('descripcion', 'like', '%' . $request->buscar . '%');
        }

        return response()->json($q->paginate(100));
    }

    // ── Listado detallado para el módulo (con conteo de movimientos y totales) ─
    public function detalle(Request $request)
    {
        $q = DB::table('ingresos_manuales as i')
            ->leftJoin(
                DB::raw('(SELECT ingreso_id, COUNT(*) as cnt FROM ingreso_movimiento GROUP BY ingreso_id) as im'),
                'i.id', '=', 'im.ingreso_id'
            )
            ->select(
                'i.id', 'i.fecha', 'i.descripcion', 'i.monto',
                'i.categoria', 'i.notas', 'i.created_at',
                DB::raw('COALESCE(im.cnt, 0) as movimientos_count')
            )
            ->orderByDesc('i.fecha')
            ->orderByDesc('i.id');

        if ($request->filled('desde')) {
            $q->where('i.fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $q->where('i.fecha', '<=', $request->hasta);
        }
        if ($request->filled('categoria')) {
            $q->where('i.categoria', $request->categoria);
        }
        if ($request->filled('buscar')) {
            $q->where('i.descripcion', 'like', '%' . $request->buscar . '%');
        }

        $items = $q->get();

        // Totales
        $totalMonto    = $items->sum('monto');
        $totalCantidad = $items->count();
        $conMovimiento = $items->where('movimientos_count', '>', 0)->count();

        return response()->json([
            'items'   => $items,
            'totales' => [
                'total_monto'    => $totalMonto,
                'total_cantidad' => $totalCantidad,
                'con_movimiento' => $conMovimiento,
                'sin_movimiento' => $totalCantidad - $conMovimiento,
            ],
        ]);
    }

    // ── Crear ingreso manual (standalone) ─────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'fecha'       => 'required|date',
            'monto'       => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string|max:255',
            'categoria'   => 'nullable|string|max:100',
            'notas'       => 'nullable|string',
        ]);

        $ingreso = IngresoManual::create($request->only([
            'fecha', 'monto', 'descripcion', 'categoria', 'notas',
        ]));

        return response()->json($ingreso, 201);
    }

    // ── Actualizar ────────────────────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $ingreso = IngresoManual::findOrFail($id);
        $ingreso->update($request->only([
            'fecha', 'monto', 'descripcion', 'categoria', 'notas',
        ]));
        return response()->json($ingreso);
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────
    public function destroy(int $id)
    {
        $ingreso = IngresoManual::findOrFail($id);

        // Desmarcar movimientos vinculados si quedan descubiertos
        $movIds = DB::table('ingreso_movimiento')
            ->where('ingreso_id', $id)
            ->pluck('movimiento_id');

        $ingreso->delete(); // cascade elimina pivots

        foreach ($movIds as $movId) {
            $this->recalcularConciliado($movId);
        }

        return response()->json(null, 204);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Perspectiva desde el MOVIMIENTO (para conciliación de créditos)
    // ══════════════════════════════════════════════════════════════════════════

    // ── Ingresos asignados a un movimiento crédito ────────────────────────────
    public function indexPorMovimiento(int $movimientoId)
    {
        $asignados = DB::table('ingreso_movimiento as im')
            ->join('ingresos_manuales as ing', 'ing.id', '=', 'im.ingreso_id')
            ->where('im.movimiento_id', $movimientoId)
            ->select(
                'im.id as pivot_id',
                'im.monto as monto_asignado',
                'ing.id as ingreso_id',
                'ing.fecha',
                'ing.descripcion',
                'ing.monto',
                'ing.categoria',
            )
            ->get();

        return response()->json(['asignados' => $asignados]);
    }

    // ── Crear ingreso manual + vincularlo al movimiento ───────────────────────
    public function storePorMovimiento(Request $request, int $movimientoId)
    {
        $request->validate([
            'descripcion' => 'nullable|string|max:255',
            'categoria'   => 'nullable|string|max:100',
            'notas'       => 'nullable|string',
        ]);

        // Obtener el movimiento para tomar fecha y monto
        $mov = DB::table('movimientos_bancarios')->where('id', $movimientoId)->firstOrFail();

        $ingreso = IngresoManual::create([
            'fecha'       => $mov->fecha_contable,
            'descripcion' => $request->descripcion ?? $mov->descripcion,
            'monto'       => $mov->monto,
            'categoria'   => $request->categoria ?? 'Ingreso',
            'notas'       => $request->notas,
        ]);

        DB::table('ingreso_movimiento')->insert([
            'ingreso_id'    => $ingreso->id,
            'movimiento_id' => $movimientoId,
            'monto'         => $mov->monto,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Marcar el movimiento como conciliado (crear ingreso = registrar este ingreso)
        DB::table('movimientos_bancarios')
            ->where('id', $movimientoId)
            ->update(['conciliado' => true]);

        return response()->json($ingreso, 201);
    }

    // ── Desvincular (y eliminar el ingreso si queda huérfano) ─────────────────
    public function destroyPorMovimiento(int $movimientoId, int $pivotId)
    {
        $pivot = DB::table('ingreso_movimiento')
            ->where('id', $pivotId)
            ->where('movimiento_id', $movimientoId)
            ->firstOrFail();

        $ingresoId = $pivot->ingreso_id;
        DB::table('ingreso_movimiento')->where('id', $pivotId)->delete();

        // Si el ingreso quedó sin movimientos vinculados, eliminarlo
        $restantes = DB::table('ingreso_movimiento')->where('ingreso_id', $ingresoId)->count();
        if ($restantes === 0) {
            IngresoManual::where('id', $ingresoId)->delete();
        }

        // Recalcular conciliado del movimiento
        $this->recalcularConciliado($movimientoId);

        return response()->json(null, 204);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function recalcularConciliado(int $movimientoId): void
    {
        $monto = DB::table('movimientos_bancarios')->where('id', $movimientoId)->value('monto');

        $totalVentas   = DB::table('venta_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
        $totalIngresos = DB::table('ingreso_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
        $totalAsignado = $totalVentas + $totalIngresos;

        $conciliado = $totalAsignado >= $monto;
        DB::table('movimientos_bancarios')
            ->where('id', $movimientoId)
            ->update(['conciliado' => $conciliado]);
    }
}
