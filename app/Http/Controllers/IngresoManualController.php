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
                DB::raw('(SELECT ingreso_id, COUNT(*) as cnt, SUM(monto) as asignado FROM ingreso_movimiento GROUP BY ingreso_id) as im'),
                'i.id', '=', 'im.ingreso_id'
            )
            ->select(
                'i.id', 'i.fecha', 'i.descripcion', 'i.monto',
                'i.categoria', 'i.notas', 'i.created_at', 'i.cotizacion_id',
                DB::raw('COALESCE(im.cnt, 0) as movimientos_count'),
                DB::raw('COALESCE(im.asignado, 0) as asignado'),
                DB::raw('(i.monto - COALESCE(im.asignado, 0)) as pendiente')
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
    // Ingresos manuales vinculados a una cotización (creados desde Operaciones) con
    // saldo aún SIN asignar a movimientos bancarios. Una nota puede cubrirse con varias
    // transferencias (varios movimientos), por eso sigue pendiente mientras quede saldo.
    public function notasVentaPendientes(Request $request)
    {
        $q = DB::table('ingresos_manuales as i')
            ->leftJoin('cotizaciones as c', 'c.id', '=', 'i.cotizacion_id')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->leftJoin(
                DB::raw('(SELECT ingreso_id, SUM(monto) asignado FROM ingreso_movimiento GROUP BY ingreso_id) as im'),
                'im.ingreso_id', '=', 'i.id'
            )
            ->whereNotNull('i.cotizacion_id')
            ->whereRaw('i.monto - COALESCE(im.asignado, 0) > 0.5')
            ->select(
                'i.id', 'i.fecha', 'i.monto', 'i.descripcion', 'i.cotizacion_id',
                DB::raw('COALESCE(im.asignado, 0) as asignado'),
                DB::raw('(i.monto - COALESCE(im.asignado, 0)) as pendiente'),
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

        // Saldo por asignar DEL MOVIMIENTO
        $asignadoMov = DB::table('venta_movimiento')->where('movimiento_id', $movimientoId)->sum('monto')
                     + DB::table('ingreso_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
        $saldoMov = (float) $mov->monto - (float) $asignadoMov;
        // Saldo por asignar DE LA NOTA (puede cubrirse con varias transferencias)
        $asignadoNota = DB::table('ingreso_movimiento')->where('ingreso_id', $ingreso->id)->sum('monto');
        $saldoNota    = (float) $ingreso->monto - (float) $asignadoNota;

        $monto = min(max(0, $saldoNota), max(0, $saldoMov));
        if ($monto <= 0) {
            $msg = $saldoNota <= 0
                ? 'La nota de venta ya está completamente conciliada'
                : 'El movimiento ya está completamente asignado';
            return response()->json(['message' => $msg], 422);
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

    // ── Movimientos crédito con saldo por asignar (para conciliar un ingreso) ──
    // Perspectiva inversa: desde un ingreso/nota de venta, elegir el movimiento.
    public function movimientosCreditoDisponibles(Request $request)
    {
        $vm = DB::raw('(SELECT movimiento_id, SUM(monto) t FROM venta_movimiento GROUP BY movimiento_id) as vm');
        $im = DB::raw('(SELECT movimiento_id, SUM(monto) t FROM ingreso_movimiento GROUP BY movimiento_id) as im');

        $q = DB::table('movimientos_bancarios as m')
            ->leftJoin($vm, 'vm.movimiento_id', '=', 'm.id')
            ->leftJoin($im, 'im.movimiento_id', '=', 'm.id')
            ->where('m.tipo', 'C')
            ->whereRaw('m.monto - COALESCE(vm.t,0) - COALESCE(im.t,0) > 0.5')
            ->select(
                'm.id', 'm.fecha_contable', 'm.descripcion', 'm.monto', 'm.cuenta',
                DB::raw('(m.monto - COALESCE(vm.t,0) - COALESCE(im.t,0)) as saldo')
            );

        if ($request->filled('buscar')) {
            $q->where('m.descripcion', 'like', '%' . $request->buscar . '%');
        }
        if ($request->filled('desde')) {
            $q->where('m.fecha_contable', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $q->where('m.fecha_contable', '<=', $request->hasta);
        }

        // Priorizar los movimientos cuyo saldo esté más cerca de lo que falta por conciliar
        if ($request->filled('cerca_de')) {
            $target = (float) $request->cerca_de;
            $q->orderByRaw('ABS((m.monto - COALESCE(vm.t,0) - COALESCE(im.t,0)) - ?) asc', [$target]);
        } else {
            $q->orderByDesc('m.fecha_contable');
        }

        return response()->json(['movimientos' => $q->limit(80)->get()]);
    }

    // ── Conciliaciones (movimientos) ya vinculadas a un ingreso ────────────────
    public function conciliacionesDeIngreso(int $id)
    {
        $ing = IngresoManual::findOrFail($id);

        $asignados = DB::table('ingreso_movimiento as im')
            ->join('movimientos_bancarios as m', 'm.id', '=', 'im.movimiento_id')
            ->where('im.ingreso_id', $id)
            ->select(
                'im.id as pivot_id', 'im.monto as monto_asignado',
                'm.id as movimiento_id', 'm.fecha_contable', 'm.descripcion', 'm.monto as movimiento_monto'
            )
            ->orderByDesc('m.fecha_contable')
            ->get();

        $asignado = (float) $asignados->sum('monto_asignado');

        return response()->json([
            'ingreso' => [
                'id'          => $ing->id,
                'descripcion' => $ing->descripcion,
                'monto'       => (float) $ing->monto,
                'asignado'    => $asignado,
                'pendiente'   => (float) $ing->monto - $asignado,
            ],
            'asignados' => $asignados,
        ]);
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
