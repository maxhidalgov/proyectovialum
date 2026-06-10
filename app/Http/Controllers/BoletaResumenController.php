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

        return response()->json(['ok' => true]);
    }

    // ── DELETE /api/boletas/resumenes/movimiento/{pivotId} ───────────────────

    public function desvincular(int $pivotId)
    {
        $pivot = DB::table('boleta_resumen_movimiento')->where('id', $pivotId)->first();
        if (!$pivot) return response()->json(['error' => 'No encontrado'], 404);

        $resumenId = $pivot->boleta_resumen_id;

        DB::table('boleta_resumen_movimiento')->where('id', $pivotId)->delete();

        $this->recalcularConciliado($resumenId);

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
}
