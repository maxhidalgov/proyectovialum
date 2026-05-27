<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentasPorPagarController extends Controller
{
    // ── Resumen por proveedor ────────────────────────────────────────────────

    public function index(Request $request)
    {
        $desde  = $request->get('desde');
        $hasta  = $request->get('hasta');
        $buscar = $request->get('buscar');
        $solo_pendientes = filter_var($request->get('solo_pendientes', true), FILTER_VALIDATE_BOOLEAN);

        $q = DB::table('compras')
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) as monto_pagado FROM compra_movimiento GROUP BY compra_id) as pagos'),
                'compras.id', '=', 'pagos.compra_id'
            )
            ->select(
                'compras.rut_emisor',
                'compras.nombre_emisor',
                DB::raw('COUNT(compras.id) as cantidad_facturas'),
                DB::raw('SUM(compras.total) as total_facturas'),
                DB::raw('COALESCE(SUM(pagos.monto_pagado), 0) as total_pagado'),
                DB::raw('SUM(compras.total) - COALESCE(SUM(pagos.monto_pagado), 0) as total_pendiente')
            )
            ->groupBy('compras.rut_emisor', 'compras.nombre_emisor');

        if ($desde) $q->where('compras.fecha_emision', '>=', $desde);
        if ($hasta) $q->where('compras.fecha_emision', '<=', $hasta);
        if ($buscar) $q->where(function ($sq) use ($buscar) {
            $sq->where('compras.nombre_emisor', 'like', "%$buscar%")
               ->orWhere('compras.rut_emisor', 'like', "%$buscar%");
        });

        if ($solo_pendientes) {
            $q->havingRaw('total_pendiente > 0');
        }

        $proveedores = $q->orderByDesc('total_pendiente')->get();

        // Totales globales
        $totales = DB::table('compras')
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) as monto_pagado FROM compra_movimiento GROUP BY compra_id) as pagos'),
                'compras.id', '=', 'pagos.compra_id'
            )
            ->selectRaw('
                COUNT(compras.id) as total_facturas,
                SUM(compras.total) as total_monto,
                COALESCE(SUM(pagos.monto_pagado), 0) as total_pagado,
                SUM(compras.total) - COALESCE(SUM(pagos.monto_pagado), 0) as total_pendiente,
                COUNT(DISTINCT compras.rut_emisor) as total_proveedores
            ')
            ->when($desde, fn($sq) => $sq->where('compras.fecha_emision', '>=', $desde))
            ->when($hasta, fn($sq) => $sq->where('compras.fecha_emision', '<=', $hasta))
            ->first();

        return response()->json([
            'proveedores' => $proveedores,
            'totales'     => $totales,
        ]);
    }

    // ── Facturas de un proveedor ─────────────────────────────────────────────

    public function facturas(Request $request, string $rut)
    {
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');
        $solo_pendientes = filter_var($request->get('solo_pendientes', true), FILTER_VALIDATE_BOOLEAN);

        $facturas = DB::table('compras')
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) as monto_pagado FROM compra_movimiento GROUP BY compra_id) as pagos'),
                'compras.id', '=', 'pagos.compra_id'
            )
            ->where('compras.rut_emisor', $rut)
            ->select(
                'compras.id',
                'compras.folio',
                'compras.tipo_dte',
                'compras.fecha_emision',
                'compras.neto',
                'compras.iva',
                'compras.total',
                'compras.estado',
                'compras.pdf_url',
                DB::raw('COALESCE(pagos.monto_pagado, 0) as monto_pagado'),
                DB::raw('compras.total - COALESCE(pagos.monto_pagado, 0) as pendiente')
            )
            ->when($desde, fn($q) => $q->where('compras.fecha_emision', '>=', $desde))
            ->when($hasta, fn($q) => $q->where('compras.fecha_emision', '<=', $hasta))
            ->when($solo_pendientes, fn($q) => $q->havingRaw('pendiente > 0'))
            ->orderByDesc('compras.fecha_emision')
            ->get();

        return response()->json($facturas);
    }
}
