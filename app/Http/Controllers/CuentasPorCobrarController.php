<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentasPorCobrarController extends Controller
{
    // ── Resumen por cliente ──────────────────────────────────────────────────
    //
    // Soporta dos orígenes de documentos:
    //   1. Creados desde el app (cotizacion_id NOT NULL → cliente via cotizaciones)
    //   2. Sincronizados desde Bsale (cotizacion_id NULL → cliente via cliente_id o bsale_cliente_*)

    public function index(Request $request)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $buscar         = $request->get('buscar');
        $soloPendientes = filter_var($request->get('solo_pendientes', true), FILTER_VALIDATE_BOOLEAN);

        $cobradoSub = DB::raw('(SELECT venta_id, SUM(monto) as monto_cobrado FROM venta_movimiento GROUP BY venta_id) as cobros');

        $q = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c', 'c.id', '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_cot', 'cl_cot.id', '=', 'c.cliente_id')      // vía cotización
            ->leftJoin('clientes as cl_dir', 'cl_dir.id', '=', 'df.cliente_id')     // vía sync directo
            ->leftJoin($cobradoSub, 'df.id', '=', 'cobros.venta_id')
            ->where('df.estado', 'emitido')
            ->select(
                DB::raw('COALESCE(cl_dir.id, cl_cot.id) as cliente_id'),
                DB::raw('COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre) as razon_social'),
                DB::raw('COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut) as identification'),
                DB::raw('COUNT(df.id) as cantidad_facturas'),
                DB::raw('SUM(df.monto) as total_facturado'),
                DB::raw('COALESCE(SUM(cobros.monto_cobrado), 0) as total_cobrado'),
                DB::raw('SUM(df.monto) - COALESCE(SUM(cobros.monto_cobrado), 0) as total_pendiente')
            )
            ->groupBy(
                DB::raw('COALESCE(cl_dir.id, cl_cot.id)'),
                DB::raw('COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre)'),
                DB::raw('COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut)')
            );

        if ($desde)  $q->where('df.fecha_emision', '>=', $desde);
        if ($hasta)  $q->where('df.fecha_emision', '<=', $hasta);
        if ($buscar) {
            $q->where(function ($sq) use ($buscar) {
                $sq->where('cl_cot.razon_social', 'like', "%$buscar%")
                   ->orWhere('cl_dir.razon_social', 'like', "%$buscar%")
                   ->orWhere('df.bsale_cliente_nombre', 'like', "%$buscar%")
                   ->orWhere('cl_cot.identification', 'like', "%$buscar%")
                   ->orWhere('cl_dir.identification', 'like', "%$buscar%")
                   ->orWhere('df.bsale_cliente_rut', 'like', "%$buscar%");
            });
        }
        if ($soloPendientes) {
            $q->havingRaw('total_pendiente > 0');
        }

        $clientes = $q->orderByDesc('total_pendiente')->get();

        // Totales globales
        $totalesQ = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c', 'c.id', '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_cot', 'cl_cot.id', '=', 'c.cliente_id')
            ->leftJoin('clientes as cl_dir', 'cl_dir.id', '=', 'df.cliente_id')
            ->leftJoin($cobradoSub, 'df.id', '=', 'cobros.venta_id')
            ->where('df.estado', 'emitido');

        if ($desde) $totalesQ->where('df.fecha_emision', '>=', $desde);
        if ($hasta) $totalesQ->where('df.fecha_emision', '<=', $hasta);

        $totales = $totalesQ->selectRaw('
            COUNT(df.id) as total_facturas,
            COUNT(DISTINCT COALESCE(cl_dir.id, cl_cot.id)) as total_clientes,
            SUM(df.monto) as total_facturado,
            COALESCE(SUM(cobros.monto_cobrado), 0) as total_cobrado,
            SUM(df.monto) - COALESCE(SUM(cobros.monto_cobrado), 0) as total_pendiente
        ')->first();

        return response()->json([
            'clientes' => $clientes,
            'totales'  => $totales,
        ]);
    }

    // ── Facturas de un cliente ───────────────────────────────────────────────

    public function facturas(Request $request, int $clienteId)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $soloPendientes = filter_var($request->get('solo_pendientes', false), FILTER_VALIDATE_BOOLEAN);

        $cobradoSub = DB::raw('(SELECT venta_id, SUM(monto) as monto_cobrado FROM venta_movimiento GROUP BY venta_id) as cobros');

        $q = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c', 'c.id', '=', 'df.cotizacion_id')
            ->leftJoin($cobradoSub, 'df.id', '=', 'cobros.venta_id')
            // Docs de este cliente: vía cotización O vía cliente_id directo
            ->where(function ($sq) use ($clienteId) {
                $sq->where('c.cliente_id', $clienteId)
                   ->orWhere('df.cliente_id', $clienteId);
            })
            ->where('df.estado', 'emitido')
            ->select(
                'df.id',
                'df.cotizacion_id',
                'df.tipo',
                'df.monto',
                DB::raw('COALESCE(df.neto, df.monto) as neto'),
                'df.fecha_emision',
                'df.numero_documento_bsale',
                'df.url_pdf_bsale',
                'df.id_documento_bsale',
                'df.tipo_documento_bsale_id',
                DB::raw('COALESCE(cobros.monto_cobrado, 0) as monto_cobrado'),
                DB::raw('df.monto - COALESCE(cobros.monto_cobrado, 0) as pendiente')
            );

        if ($desde) $q->where('df.fecha_emision', '>=', $desde);
        if ($hasta) $q->where('df.fecha_emision', '<=', $hasta);
        if ($soloPendientes) $q->havingRaw('pendiente > 0');

        $facturas = $q->orderByDesc('df.fecha_emision')->get();

        return response()->json($facturas);
    }
}
