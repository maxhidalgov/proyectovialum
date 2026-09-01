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

        // Si el ingreso quedó sin movimientos vinculados, eliminarlo — SALVO que sea
        // una "nota de venta" (vinculada a una cotización): esa persiste para volver a
        // conciliarse o mostrarse en Operaciones.
        $restantes = DB::table('ingreso_movimiento')->where('ingreso_id', $ingresoId)->count();
        if ($restantes === 0) {
            $ing = IngresoManual::find($ingresoId);
            if ($ing && !$ing->cotizacion_id) {
                $ing->delete();
            }
        }

        // Recalcular conciliado del movimiento
        $this->recalcularConciliado($movimientoId);

        return response()->json(null, 204);
    }

    // ── Notas de venta pendientes de conciliar ────────────────────────────────
    // Ingresos manuales vinculados a una cotización (creados desde Operaciones) que
    // aún NO están asignados a ningún movimiento bancario.
    public function notasVentaPendientes(Request $request)
    {
        $q = DB::table('ingresos_manuales as i')
            ->leftJoin('cotizaciones as c', 'c.id', '=', 'i.cotizacion_id')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->whereNotNull('i.cotizacion_id')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))->from('ingreso_movimiento as im')
                    ->whereColumn('im.ingreso_id', 'i.id');
            })
            ->select(
                'i.id', 'i.fecha', 'i.monto', 'i.descripcion', 'i.cotizacion_id',
                DB::raw("COALESCE(cl.razon_social, TRIM(CONCAT(COALESCE(cl.first_name,''),' ',COALESCE(cl.last_name,'')))) as cliente_nombre")
            )
            ->orderByDesc('i.fecha')
            ->orderByDesc('i.id');

        return response()->json(['pendientes' => $q->get()]);
    }

    // ── Vincular una nota de venta EXISTENTE a un movimiento (conciliar) ───────
    public function vincularExistentePorMovimiento(Request $request, int $movimientoId)
    {
        $data = $request->validate([
            'ingreso_id' => 'required|integer|exists:ingresos_manuales,id',
        ]);

        $mov     = DB::table('movimientos_bancarios')->where('id', $movimientoId)->firstOrFail();
        $ingreso = IngresoManual::findOrFail($data['ingreso_id']);

        // Evitar duplicar el pivot
        $yaVinculado = DB::table('ingreso_movimiento')
            ->where('ingreso_id', $ingreso->id)
            ->where('movimiento_id', $movimientoId)
            ->exists();
        if ($yaVinculado) {
            return response()->json(['message' => 'La nota de venta ya está vinculada a este movimiento'], 422);
        }

        // Monto a asignar = lo que falta por asignar del movimiento, acotado al monto de la nota
        $asignado = DB::table('venta_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                  + DB::table('ingreso_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
        $saldo    = (float) $mov->monto - (float) $asignado;
        $monto    = min((float) $ingreso->monto, max(0, $saldo));
        if ($monto <= 0) {
            return response()->json(['message' => 'El movimiento ya está completamente asignado'], 422);
        }

        DB::table('ingreso_movimiento')->insert([
            'ingreso_id'    => $ingreso->id,
            'movimiento_id' => $movimientoId,
            'monto'         => $monto,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->recalcularConciliado($movimientoId);

        return response()->json(['success' => true, 'monto_asignado' => $monto]);
    }

    // ── Crear ingreso manual desde transacción Transbank ──────────────────────
    public function storePorTransaccion(Request $request, int $transaccionId)
    {
        $request->validate([
            'descripcion' => 'nullable|string|max:255',
            'categoria'   => 'nullable|string|max:100',
            'notas'       => 'nullable|string',
        ]);

        $tx = DB::table('transbank_transacciones')->where('id', $transaccionId)->firstOrFail();
        $fecha = $tx->fecha_movimiento
            ? \Carbon\Carbon::parse($tx->fecha_movimiento)->toDateString()
            : now()->toDateString();

        $ingreso = IngresoManual::create([
            'fecha'                    => $fecha,
            'descripcion'              => $request->descripcion ?? 'Venta Transbank sin documento',
            'monto'                    => $tx->monto_original,
            'categoria'                => $request->categoria ?? 'Ingreso',
            'notas'                    => $request->notas,
            'transbank_transaccion_id' => $transaccionId,
        ]);

        return response()->json($ingreso, 201);
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
