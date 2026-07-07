<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoletaResumenController extends Controller
{
    // ── GET /api/boletas/resumenes?periodo=YYYY-MM ────────────────────────────

    public function index(Request $request)
    {
        $periodo = $request->get('periodo');

        $resumenes = DB::table('boleta_resumenes as br')
            ->when($periodo, fn($q) => $q->where('br.periodo', $periodo))
            ->orderByDesc('br.periodo')
            ->orderBy('br.forma_pago')
            ->get();

        if ($resumenes->isNotEmpty()) {
            $ids = $resumenes->pluck('id')->all();

            $movimientos = DB::table('boleta_resumen_movimiento as brm')
                ->join('movimientos_bancarios as mb', 'mb.id', '=', 'brm.movimiento_id')
                ->whereIn('brm.boleta_resumen_id', $ids)
                ->select('brm.id', 'brm.boleta_resumen_id', 'brm.movimiento_id', 'brm.monto',
                         'mb.fecha_contable as fecha', 'mb.descripcion')
                ->get()
                ->groupBy('boleta_resumen_id');

            $resumenes = $resumenes->map(function ($r) use ($movimientos) {
                $r->movimientos = $movimientos->get($r->id, collect())->values()->all();
                return $r;
            });
        }

        $periodos = DB::table('boleta_resumenes')
            ->select('periodo')
            ->groupBy('periodo')
            ->orderByDesc('periodo')
            ->pluck('periodo');

        return response()->json([
            'resumenes' => $resumenes,
            'periodos'  => $periodos,
        ]);
    }

    // ── GET /api/boletas/resumenes/{id}/boletas ───────────────────────────────
    // Detalle de las boletas individuales del resumen

    public function boletas(int $id)
    {
        $resumen = DB::table('boleta_resumenes')->where('id', $id)->first();
        if (!$resumen) return response()->json(['error' => 'No encontrado'], 404);

        $boletas = DB::table('documentos_facturacion')
            ->whereIn('tipo_documento_bsale_id', [1])
            ->where('forma_pago', $resumen->forma_pago)
            ->whereRaw("DATE_FORMAT(fecha_emision, '%Y-%m') = ?", [$resumen->periodo])
            ->select('id', 'numero_documento_bsale', 'bsale_cliente_nombre', 'fecha_emision', 'monto', 'forma_pago')
            ->orderBy('fecha_emision')
            ->get();

        return response()->json([
            'resumen' => $resumen,
            'boletas' => $boletas,
        ]);
    }

    // ── GET /api/boletas/resumenes/{id}/estado ────────────────────────────────
    // Movimientos asignados + saldo por cobrar de un resumen (para modal CPC)

    public function estado(int $id)
    {
        $resumen = DB::table('boleta_resumenes')->where('id', $id)->firstOrFail();

        $asignados = DB::table('boleta_resumen_movimiento as brm')
            ->join('movimientos_bancarios as m', 'm.id', '=', 'brm.movimiento_id')
            ->where('brm.boleta_resumen_id', $id)
            ->select(
                'brm.id as pivot_id',
                'brm.monto as monto_asignado',
                'm.id as movimiento_id',
                'm.fecha_contable',
                'm.descripcion',
                'm.monto as monto_movimiento',
            )
            ->get();

        $totalAsignado    = $asignados->sum('monto_asignado');
        $cobradoTransbank = !empty($resumen->conciliado_transbank) ? (float) $resumen->monto_total : 0;
        $saldo            = max(0, (float) $resumen->monto_total - $totalAsignado - $cobradoTransbank);

        return response()->json([
            'asignados'         => $asignados,
            'cobrado_transbank' => $cobradoTransbank,
            'saldo_por_cobrar'  => $saldo,
        ]);
    }

    // ── GET /api/boletas/resumenes/{id}/movimientos-disponibles ───────────────
    // Movimientos crédito disponibles para asignar (para modal CPC)

    public function movimientosDisponibles(Request $request, int $id)
    {
        $resumen  = DB::table('boleta_resumenes')->where('id', $id)->firstOrFail();
        $asignado = DB::table('boleta_resumen_movimiento')->where('boleta_resumen_id', $id)->sum('monto');
        $cobradoTransbank = !empty($resumen->conciliado_transbank) ? (float) $resumen->monto_total : 0;
        $saldo    = max(0, (float) $resumen->monto_total - $asignado - $cobradoTransbank);
        $buscar   = $request->get('buscar');

        $movs = DB::table('movimientos_bancarios as m')
            ->leftJoin(
                DB::raw('(SELECT movimiento_id, SUM(monto) as asignado FROM boleta_resumen_movimiento GROUP BY movimiento_id) as brm'),
                'm.id', '=', 'brm.movimiento_id'
            )
            ->leftJoin(
                DB::raw('(SELECT movimiento_id, SUM(monto) as asignado FROM venta_movimiento GROUP BY movimiento_id) as vm'),
                'm.id', '=', 'vm.movimiento_id'
            )
            ->where('m.tipo', 'C')
            ->where('m.conciliado', 0)
            ->whereRaw('m.monto - COALESCE(brm.asignado, 0) - COALESCE(vm.asignado, 0) > 0')
            ->select(
                'm.id',
                'm.fecha_contable',
                'm.descripcion',
                'm.glosa',
                'm.monto',
                DB::raw('m.monto - COALESCE(brm.asignado, 0) - COALESCE(vm.asignado, 0) as saldo_por_asignar')
            )
            ->when($buscar, fn($q) => $q->where('m.descripcion', 'like', "%$buscar%"))
            ->orderByRaw('ABS(m.monto - COALESCE(brm.asignado, 0) - COALESCE(vm.asignado, 0) - ?) ASC', [$saldo])
            ->paginate(30);

        return response()->json($movs);
    }

    // ── POST /api/boletas/resumenes/{id}/conciliar ────────────────────────────
    // Vincula el resumen a un movimiento bancario

    public function conciliar(Request $request, int $id)
    {
        $request->validate([
            'movimiento_id' => 'required|exists:movimientos_bancarios,id',
            'monto'         => 'required|numeric|min:1',
        ]);

        $resumen = DB::table('boleta_resumenes')->where('id', $id)->first();
        if (!$resumen) return response()->json(['error' => 'No encontrado'], 404);

        $movId = (int) $request->input('movimiento_id');
        $monto = (float) $request->input('monto');

        // Evitar duplicado
        if (DB::table('boleta_resumen_movimiento')
            ->where('boleta_resumen_id', $id)
            ->where('movimiento_id', $movId)
            ->exists()) {
            return response()->json(['error' => 'Ya está vinculado a ese movimiento'], 422);
        }

        DB::table('boleta_resumen_movimiento')->insert([
            'boleta_resumen_id' => $id,
            'movimiento_id'     => $movId,
            'monto'             => $monto,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->recalcularConciliado($id);
        $this->actualizarConciliadoMovimiento($movId);

        return response()->json(['ok' => true]);
    }

    // ── DELETE /api/boletas/resumenes/movimiento/{pivotId} ───────────────────

    public function desvincular(int $pivotId)
    {
        $pivot = DB::table('boleta_resumen_movimiento')->where('id', $pivotId)->first();
        if (!$pivot) return response()->json(['error' => 'No encontrado'], 404);

        $resumenId = $pivot->boleta_resumen_id;
        $movId     = $pivot->movimiento_id;

        DB::table('boleta_resumen_movimiento')->where('id', $pivotId)->delete();

        $this->recalcularConciliado($resumenId);
        $this->actualizarConciliadoMovimiento($movId);

        return response()->json(['ok' => true]);
    }

    // ── POST /api/boletas/resumenes/recalcular ────────────────────────────────
    // Dispara el comando artisan para regenerar los resúmenes del periodo

    public function recalcular(Request $request)
    {
        $periodo = $request->get('periodo');

        \Artisan::call('boletas:recalcular-resumenes', $periodo ? ['--periodo' => $periodo] : []);

        return response()->json([
            'ok'     => true,
            'output' => \Artisan::output(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Perspectiva desde el MOVIMIENTO (para conciliar crédito ↔ boletas/mes)
    // ══════════════════════════════════════════════════════════════════════════

    // ── GET /api/conciliacion/movimientos/{id}/boletas ────────────────────────
    // Resúmenes (mes + forma de pago) ya asignados a este movimiento
    public function asignadosPorMovimiento(int $movId)
    {
        $asignados = DB::table('boleta_resumen_movimiento as brm')
            ->join('boleta_resumenes as br', 'br.id', '=', 'brm.boleta_resumen_id')
            ->where('brm.movimiento_id', $movId)
            ->select(
                'brm.id as pivot_id',
                'br.id as resumen_id',
                'br.periodo',
                'br.forma_pago',
                'brm.monto as monto_asignado'
            )
            ->orderBy('br.periodo')
            ->orderBy('br.forma_pago')
            ->get();

        return response()->json(['asignados' => $asignados]);
    }

    // ── GET /api/conciliacion/movimientos/{id}/boletas-disponibles ────────────
    // Resúmenes por mes Y forma de pago, con su saldo por cobrar
    public function disponiblesPorMovimiento(int $movId)
    {
        $resumenes = DB::table('boleta_resumenes as br')
            ->leftJoin(
                DB::raw('(SELECT boleta_resumen_id, SUM(monto) as asignado FROM boleta_resumen_movimiento GROUP BY boleta_resumen_id) as brm'),
                'br.id', '=', 'brm.boleta_resumen_id'
            )
            ->select(
                'br.id as resumen_id',
                'br.periodo',
                'br.forma_pago',
                'br.total_boletas',
                'br.monto_total',
                'br.conciliado_transbank',
                DB::raw('COALESCE(brm.asignado, 0) as asignado')
            )
            ->orderByDesc('br.periodo')
            ->orderBy('br.forma_pago')
            ->get();

        $result = $resumenes->map(function ($r) {
            $transbank = !empty($r->conciliado_transbank) ? (float) $r->monto_total : 0;
            $saldo     = max(0, (float) $r->monto_total - (float) $r->asignado - $transbank);
            return [
                'resumen_id'       => $r->resumen_id,
                'periodo'          => $r->periodo,
                'forma_pago'       => $r->forma_pago,
                'total_boletas'    => $r->total_boletas,
                'monto_total'      => (float) $r->monto_total,
                'saldo_por_cobrar' => $saldo,
            ];
        })
        ->filter(fn ($r) => $r['saldo_por_cobrar'] > 0)
        ->values();

        return response()->json($result);
    }

    // ── POST /api/conciliacion/movimientos/{id}/boletas ───────────────────────
    // Vincula el movimiento a un resumen concreto (mes + forma de pago)
    public function vincularPorMovimiento(Request $request, int $movId)
    {
        $request->validate([
            'resumen_id' => 'required|integer|exists:boleta_resumenes,id',
            'monto'      => 'required|numeric|min:0.01',
        ]);

        DB::table('boleta_resumen_movimiento')->updateOrInsert(
            ['boleta_resumen_id' => $request->resumen_id, 'movimiento_id' => $movId],
            ['monto' => $request->monto, 'updated_at' => now(), 'created_at' => now()]
        );

        $this->recalcularConciliado((int) $request->resumen_id);
        $this->actualizarConciliadoMovimiento($movId);

        return response()->json(['ok' => true], 201);
    }

    // ── DELETE /api/conciliacion/movimientos/{id}/boletas/{pivotId} ───────────
    public function destroyPorMovimiento(int $movId, int $pivotId)
    {
        $pivot = DB::table('boleta_resumen_movimiento')
            ->where('id', $pivotId)
            ->where('movimiento_id', $movId)
            ->first();

        if ($pivot) {
            DB::table('boleta_resumen_movimiento')->where('id', $pivotId)->delete();
            $this->recalcularConciliado((int) $pivot->boleta_resumen_id);
            $this->actualizarConciliadoMovimiento($movId);
        }

        return response()->json(null, 204);
    }

    private function actualizarConciliadoMovimiento(int $movId): void
    {
        $totalAsignado = (float) DB::table('venta_movimiento')->where('movimiento_id', $movId)->sum('monto')
                       + (float) DB::table('boleta_periodo_movimiento')->where('movimiento_id', $movId)->sum('monto')
                       + (float) DB::table('boleta_resumen_movimiento')->where('movimiento_id', $movId)->sum('monto');

        $monto = (float) DB::table('movimientos_bancarios')->where('id', $movId)->value('monto');

        DB::table('movimientos_bancarios')
            ->where('id', $movId)
            ->update(['conciliado' => $totalAsignado >= $monto]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function recalcularConciliado(int $resumenId): void
    {
        $resumen       = DB::table('boleta_resumenes')->where('id', $resumenId)->first();
        $totalVinculado = DB::table('boleta_resumen_movimiento')
            ->where('boleta_resumen_id', $resumenId)
            ->sum('monto');

        DB::table('boleta_resumenes')->where('id', $resumenId)->update([
            'conciliado' => (float) $totalVinculado >= (float) $resumen->monto_total,
            'updated_at' => now(),
        ]);
    }

    // ── PATCH /api/boletas/resumen/{id}/conciliar-transbank ───────────────────
    // Marca/desmarca un resumen como conciliado vía Transbank (boletas tarjeta).
    public function toggleConciliadoTransbank(int $id)
    {
        $resumen = DB::table('boleta_resumenes')->where('id', $id)->first();
        if (!$resumen) return response()->json(['error' => 'No encontrado'], 404);

        $nuevo = !$resumen->conciliado_transbank;
        DB::table('boleta_resumenes')->where('id', $id)->update([
            'conciliado_transbank' => $nuevo,
            'updated_at'           => now(),
        ]);

        return response()->json([
            'id'                    => $id,
            'conciliado_transbank'  => $nuevo,
            'forma_pago'            => $resumen->forma_pago,
            'monto_total'           => $resumen->monto_total,
        ]);
    }
}
