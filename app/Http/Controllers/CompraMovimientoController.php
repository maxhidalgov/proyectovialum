<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\MovimientoBancario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraMovimientoController extends Controller
{
    // ── Movimientos ya asignados a una factura ────────────────────────────────

    public function index(int $compraId)
    {
        $compra = Compra::findOrFail($compraId);

        $asignados = DB::table('compra_movimiento')
            ->join('movimientos_bancarios', 'movimientos_bancarios.id', '=', 'compra_movimiento.movimiento_id')
            ->where('compra_movimiento.compra_id', $compraId)
            ->select(
                'compra_movimiento.id as pivot_id',
                'compra_movimiento.monto as monto_asignado',
                'movimientos_bancarios.id',
                'movimientos_bancarios.fecha_contable',
                'movimientos_bancarios.descripcion',
                'movimientos_bancarios.monto',
                'movimientos_bancarios.glosa',
                DB::raw("'banco' as tipo_asignado"),
            )
            ->get();

        // NCs aplicadas a esta factura
        $ncsAplicadas = DB::table('compra_nc_aplicacion')
            ->join('compras as nc', 'nc.id', '=', 'compra_nc_aplicacion.nc_id')
            ->where('compra_nc_aplicacion.factura_id', $compraId)
            ->select(
                'compra_nc_aplicacion.id as pivot_id',
                'compra_nc_aplicacion.monto as monto_asignado',
                'compra_nc_aplicacion.fecha as fecha_contable',
                DB::raw("CONCAT('NC #', nc.folio) as descripcion"),
                'compra_nc_aplicacion.nota as glosa',
                DB::raw("'nc' as tipo_asignado"),
            )
            ->get();

        $totalBanco = $asignados->sum('monto_asignado');
        $totalNC    = $ncsAplicadas->sum('monto_asignado');
        $saldo_por_pagar = max(0, $compra->total - $totalBanco - $totalNC);

        return response()->json([
            'compra'          => $compra,
            'asignados'       => $asignados,
            'ncs_aplicadas'   => $ncsAplicadas,
            'saldo_por_pagar' => $saldo_por_pagar,
        ]);
    }

    // ── Movimientos disponibles para asignar ──────────────────────────────────

    public function disponibles(Request $request, int $compraId)
    {
        $compra  = Compra::findOrFail($compraId);
        $buscar  = $request->get('buscar');
        $monto   = $request->get('monto');

        // Saldo pendiente de la compra para ordenar por proximidad de monto
        $bancoPagado = DB::table('compra_movimiento')->where('compra_id', $compraId)->sum('monto');
        $ncPagado    = DB::table('compra_nc_aplicacion')->where('factura_id', $compraId)->sum('monto');
        $saldoPendiente = max(0, (float) $compra->total - (float) $bancoPagado - (float) $ncPagado);

        // Saldo por asignar por movimiento = monto - SUM ya asignado en pivot
        $movimientos = DB::table('movimientos_bancarios')
            ->leftJoin(
                DB::raw('(SELECT movimiento_id, SUM(monto) as asignado FROM compra_movimiento GROUP BY movimiento_id) as asig'),
                'movimientos_bancarios.id', '=', 'asig.movimiento_id'
            )
            ->where('movimientos_bancarios.tipo', 'D') // solo débitos (egresos)
            ->whereNull('movimientos_bancarios.fecha_contable') // placeholder; eliminamos esta condición
            ->select(
                'movimientos_bancarios.id',
                'movimientos_bancarios.fecha_contable',
                'movimientos_bancarios.descripcion',
                'movimientos_bancarios.glosa',
                'movimientos_bancarios.monto',
                DB::raw('movimientos_bancarios.monto - COALESCE(asig.asignado, 0) as saldo_por_asignar')
            )
            ->havingRaw('saldo_por_asignar > 0')
            ->orderByDesc('movimientos_bancarios.fecha_contable');

        // Quitar el whereNull placeholder (fue un error) — reconstruimos la query limpia
        $movimientos = DB::table('movimientos_bancarios')
            ->leftJoin(
                DB::raw('(SELECT movimiento_id, SUM(monto) as asignado FROM compra_movimiento GROUP BY movimiento_id) as asig'),
                'movimientos_bancarios.id', '=', 'asig.movimiento_id'
            )
            // Excluir el movimiento ya asignado a ESTA compra
            ->whereNotExists(function ($q) use ($compraId) {
                $q->from('compra_movimiento')
                  ->whereColumn('compra_movimiento.movimiento_id', 'movimientos_bancarios.id')
                  ->where('compra_movimiento.compra_id', $compraId);
            })
            ->where('movimientos_bancarios.tipo', 'D')
            ->when($buscar, function ($q) use ($buscar) {
                $q->where(function ($sq) use ($buscar) {
                    $sq->where('movimientos_bancarios.descripcion', 'like', "%$buscar%")
                       ->orWhere('movimientos_bancarios.glosa', 'like', "%$buscar%");
                });
            })
            ->when($monto, function ($q) use ($monto) {
                $montoNum = (int) preg_replace('/[^0-9]/', '', $monto);
                if ($montoNum > 0) $q->whereRaw('ROUND(movimientos_bancarios.monto) = ?', [$montoNum]);
            })
            ->select(
                'movimientos_bancarios.id',
                'movimientos_bancarios.fecha_contable',
                'movimientos_bancarios.descripcion',
                'movimientos_bancarios.glosa',
                'movimientos_bancarios.monto',
                DB::raw('movimientos_bancarios.monto - COALESCE(asig.asignado, 0) as saldo_por_asignar')
            )
            ->havingRaw('saldo_por_asignar > 0')
            ->orderByRaw('ABS(movimientos_bancarios.monto - COALESCE(asig.asignado, 0) - ?) ASC', [$saldoPendiente])
            ->orderByDesc('movimientos_bancarios.fecha_contable')
            ->paginate(50);

        return response()->json($movimientos);
    }

    // ── Asignar movimiento a factura ──────────────────────────────────────────

    public function store(Request $request, int $compraId)
    {
        $compra = Compra::findOrFail($compraId);

        $request->validate([
            'movimiento_id' => 'required|exists:movimientos_bancarios,id',
            'monto'         => 'required|numeric|min:0.01',
        ]);

        $movimiento = MovimientoBancario::findOrFail($request->movimiento_id);

        // Verificar que el monto no supere el saldo disponible del movimiento
        $asignadoMov = DB::table('compra_movimiento')
            ->where('movimiento_id', $movimiento->id)
            ->sum('monto');
        $saldoMov = $movimiento->monto - $asignadoMov;

        // Verificar que el monto no supere el saldo pendiente de la factura
        $asignadoComp = DB::table('compra_movimiento')
            ->where('compra_id', $compraId)
            ->sum('monto');
        $saldoComp = $compra->total - $asignadoComp;

        $monto = min($request->monto, $saldoMov, $saldoComp);
        if ($monto <= 0) {
            return response()->json(['error' => 'No hay saldo disponible para asignar'], 422);
        }

        $pivot = DB::table('compra_movimiento')->insertGetId([
            'compra_id'     => $compraId,
            'movimiento_id' => $movimiento->id,
            'monto'         => $monto,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Marcar movimiento como conciliado si quedó sin saldo
        $asignadoMov += $monto;
        if ($asignadoMov >= $movimiento->monto) {
            $movimiento->update(['conciliado' => true]);
        }

        return response()->json([
            'pivot_id'        => $pivot,
            'monto_asignado'  => $monto,
            'saldo_por_pagar' => max(0, $saldoComp - $monto),
        ], 201);
    }

    // ── Desasignar ────────────────────────────────────────────────────────────

    public function destroy(int $compraId, int $pivotId)
    {
        $deleted = DB::table('compra_movimiento')
            ->where('id', $pivotId)
            ->where('compra_id', $compraId)
            ->delete();

        if (!$deleted) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        return response()->json(null, 204);
    }

    // ══ Perspectiva desde movimiento ══════════════════════════════════════════

    // ── Compras ya asignadas a un movimiento ──────────────────────────────────

    public function indexPorMovimiento(int $movimientoId)
    {
        $mov = MovimientoBancario::findOrFail($movimientoId);

        $asignados = DB::table('compra_movimiento')
            ->join('compras', 'compras.id', '=', 'compra_movimiento.compra_id')
            ->where('compra_movimiento.movimiento_id', $movimientoId)
            ->select(
                'compra_movimiento.id as pivot_id',
                'compra_movimiento.monto as monto_asignado',
                'compras.id',
                'compras.folio',
                'compras.tipo_dte',
                'compras.fecha_emision',
                'compras.total',
                'compras.nombre_emisor',
                'compras.rut_emisor',
            )
            ->get();

        $asignadoMov       = $asignados->sum('monto_asignado');
        $saldo_por_asignar = max(0, $mov->monto - $asignadoMov);

        return response()->json([
            'movimiento'        => $mov,
            'asignados'         => $asignados,
            'saldo_por_asignar' => $saldo_por_asignar,
        ]);
    }

    // ── Compras disponibles para asignar a un movimiento ─────────────────────

    public function disponiblesPorMovimiento(Request $request, int $movimientoId)
    {
        $mov    = MovimientoBancario::findOrFail($movimientoId);
        $buscar = $request->get('buscar');
        $orden     = $request->get('orden', 'monto');    // 'monto' | 'fecha'
        $direccion = $request->get('direccion', 'asc'); // 'asc' | 'desc'

        $asignadoMov = DB::table('compra_movimiento')
            ->where('movimiento_id', $movimientoId)
            ->sum('monto');
        $saldoMov = max(0, $mov->monto - $asignadoMov);

        $q = DB::table('compras')
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) as pagado FROM compra_movimiento GROUP BY compra_id) as pagos'),
                'compras.id', '=', 'pagos.compra_id'
            )
            ->leftJoin(
                DB::raw('(SELECT factura_id AS compra_id, SUM(monto) monto_nc FROM compra_nc_aplicacion GROUP BY factura_id) as nc_aplicado'),
                'compras.id', '=', 'nc_aplicado.compra_id'
            )
            ->leftJoin(
                DB::raw('(SELECT nc.nc_referencia_id AS compra_id, SUM(nc.total) AS monto_nc
                          FROM compras nc
                          WHERE nc.tipo_dte IN (61) AND nc.nc_referencia_id IS NOT NULL AND nc.pagado_historico = 0
                            AND NOT EXISTS (SELECT 1 FROM compra_nc_aplicacion ap WHERE ap.nc_id = nc.id)
                          GROUP BY nc.nc_referencia_id) as nc_ref'),
                'compras.id', '=', 'nc_ref.compra_id'
            )
            ->where('compras.pagado_historico', false)
            ->where('compras.tipo_dte', '!=', 61)
            ->whereNotExists(function ($q) use ($movimientoId) {
                $q->from('compra_movimiento')
                  ->whereColumn('compra_movimiento.compra_id', 'compras.id')
                  ->where('compra_movimiento.movimiento_id', $movimientoId);
            })
            ->when($buscar, function ($q) use ($buscar) {
                $q->where(function ($sq) use ($buscar) {
                    $sq->where('compras.nombre_emisor', 'like', "%$buscar%")
                       ->orWhere('compras.rut_emisor', 'like', "%$buscar%")
                       ->orWhere('compras.folio', 'like', "%$buscar%");
                });
            })
            ->select(
                'compras.id',
                'compras.folio',
                'compras.tipo_dte',
                'compras.fecha_emision',
                'compras.total',
                'compras.nombre_emisor',
                'compras.rut_emisor',
                DB::raw('compras.total
                         - COALESCE(pagos.pagado, 0)
                         - COALESCE(nc_aplicado.monto_nc, 0)
                         - COALESCE(nc_ref.monto_nc, 0) as saldo_por_pagar')
            )
            ->havingRaw('saldo_por_pagar > 0');

        if ($orden === 'fecha') {
            $dir = $direccion === 'desc' ? 'DESC' : 'ASC';
            $q->orderByRaw("compras.fecha_emision $dir")
              ->orderByRaw('ABS(saldo_por_pagar - ?) ASC', [$saldoMov]);
        } else {
            $q->orderByRaw('ABS(saldo_por_pagar - ?) ASC', [$saldoMov]);
        }

        return response()->json($q->paginate(30));
    }

    // ── Asignar compra a un movimiento ────────────────────────────────────────

    public function storePorMovimiento(Request $request, int $movimientoId)
    {
        $mov = MovimientoBancario::findOrFail($movimientoId);

        $request->validate([
            'compra_id' => 'required|exists:compras,id',
            'monto'     => 'required|numeric|min:0.01',
        ]);

        $compra = Compra::findOrFail($request->compra_id);

        if (DB::table('compra_movimiento')
            ->where('compra_id', $request->compra_id)
            ->where('movimiento_id', $movimientoId)
            ->exists()) {
            return response()->json(['error' => 'Esta compra ya está asignada a este movimiento'], 422);
        }

        $asignadoMov  = DB::table('compra_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
        $saldoMov     = $mov->monto - $asignadoMov;

        $asignadoComp = DB::table('compra_movimiento')->where('compra_id', $request->compra_id)->sum('monto');
        $saldoComp    = $compra->total - $asignadoComp;

        $monto = min($request->monto, $saldoMov, $saldoComp);
        if ($monto <= 0) {
            return response()->json(['error' => 'No hay saldo disponible para asignar'], 422);
        }

        $pivot = DB::table('compra_movimiento')->insertGetId([
            'compra_id'     => $request->compra_id,
            'movimiento_id' => $movimientoId,
            'monto'         => $monto,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $asignadoMov += $monto;
        if ($asignadoMov >= $mov->monto) {
            $mov->update(['conciliado' => true]);
        }

        return response()->json([
            'pivot_id'          => $pivot,
            'monto_asignado'    => $monto,
            'saldo_por_asignar' => max(0, $saldoMov - $monto),
        ], 201);
    }

    // ── NCs de proveedor (DTE 61) disponibles para crédito bancario ──────────
    //
    // Se usa cuando un banco muestra un CRÉDITO (abono) que corresponde
    // a una devolución de un proveedor por una nota de crédito emitida.
    // El crédito se concilia usando la misma tabla compra_movimiento.

    public function ncDisponiblesPorMovimiento(Request $request, int $movimientoId)
    {
        $mov    = MovimientoBancario::findOrFail($movimientoId);
        $buscar = $request->get('buscar');

        $asignadoMov = DB::table('compra_movimiento')
            ->where('movimiento_id', $movimientoId)
            ->sum('monto');
        $saldoMov = max(0, $mov->monto - $asignadoMov);

        $ncs = DB::table('compras')
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) as pagado FROM compra_movimiento GROUP BY compra_id) as pagos'),
                'compras.id', '=', 'pagos.compra_id'
            )
            ->leftJoin(
                DB::raw('(SELECT nc_id AS compra_id, SUM(monto) as aplicado FROM compra_nc_aplicacion GROUP BY nc_id) as nca'),
                'compras.id', '=', 'nca.compra_id'
            )
            ->where('compras.tipo_dte', 61)
            ->where('compras.pagado_historico', false)
            ->whereNotExists(function ($q) use ($movimientoId) {
                $q->from('compra_movimiento')
                  ->whereColumn('compra_movimiento.compra_id', 'compras.id')
                  ->where('compra_movimiento.movimiento_id', $movimientoId);
            })
            ->when($buscar, function ($q) use ($buscar) {
                $q->where(function ($sq) use ($buscar) {
                    $sq->where('compras.nombre_emisor', 'like', "%$buscar%")
                       ->orWhere('compras.rut_emisor',  'like', "%$buscar%")
                       ->orWhere('compras.folio',       'like', "%$buscar%");
                });
            })
            ->select(
                'compras.id',
                'compras.folio',
                'compras.tipo_dte',
                'compras.fecha_emision',
                'compras.total',
                'compras.nombre_emisor',
                'compras.rut_emisor',
                'compras.nc_referencia_id',
                DB::raw('compras.total - COALESCE(pagos.pagado, 0) - COALESCE(nca.aplicado, 0) as saldo_pendiente_nc')
            )
            ->havingRaw('saldo_pendiente_nc > 0')
            ->orderByRaw('ABS(saldo_pendiente_nc - ?) ASC', [$saldoMov])
            ->paginate(30);

        return response()->json($ncs);
    }

    // ── Desasignar compra de un movimiento ────────────────────────────────────

    public function destroyPorMovimiento(int $movimientoId, int $pivotId)
    {
        $deleted = DB::table('compra_movimiento')
            ->where('id', $pivotId)
            ->where('movimiento_id', $movimientoId)
            ->delete();

        if (!$deleted) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        $mov = MovimientoBancario::find($movimientoId);
        if ($mov) {
            $asignado = DB::table('compra_movimiento')->where('movimiento_id', $movimientoId)->sum('monto');
            if ($asignado < $mov->monto) {
                $mov->update(['conciliado' => false]);
            }
        }

        return response()->json(null, 204);
    }
}
