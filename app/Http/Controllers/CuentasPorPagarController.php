<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentasPorPagarController extends Controller
{
    // ── Tipos DTE nota de crédito ────────────────────────────────────────────
    private const TIPOS_NC = [61];

    // ── Subquery de monto pagado efectivo por compra ─────────────────────────
    //
    //  Para DTE 33/34 (facturas): banco + NC aplicada sobre esta factura
    //  Para DTE 61  (NCs)      : banco + monto de esta NC ya aplicado a alguna factura
    //
    private function efectivoPagadoSub(): \Illuminate\Database\Query\Expression
    {
        return DB::raw("(
            SELECT
                c.id AS compra_id,
                COALESCE(pb.monto_banco, 0)
                  + CASE WHEN c.tipo_dte IN (61)
                         THEN COALESCE(ncnc.monto_nc, 0)
                         ELSE COALESCE(ncf.monto_nc, 0) + COALESCE(ncref.monto_nc, 0)
                    END AS monto_pagado_efectivo
            FROM compras c
            LEFT JOIN (SELECT compra_id, SUM(monto) monto_banco FROM compra_movimiento GROUP BY compra_id) pb
                   ON pb.compra_id = c.id
            LEFT JOIN (SELECT factura_id AS compra_id, SUM(monto) monto_nc FROM compra_nc_aplicacion GROUP BY factura_id) ncf
                   ON ncf.compra_id = c.id
            LEFT JOIN (SELECT nc_id AS compra_id, SUM(monto) monto_nc FROM compra_nc_aplicacion GROUP BY nc_id) ncnc
                   ON ncnc.compra_id = c.id
            LEFT JOIN (
                SELECT nc.nc_referencia_id AS compra_id, SUM(nc.total) AS monto_nc
                FROM compras nc
                WHERE nc.tipo_dte IN (61)
                  AND nc.nc_referencia_id IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM compra_nc_aplicacion ap WHERE ap.nc_id = nc.id)
                GROUP BY nc.nc_referencia_id
            ) ncref ON ncref.compra_id = c.id
        ) AS ef");
    }

    // ── Resumen por proveedor ────────────────────────────────────────────────

    public function index(Request $request)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $buscar         = $request->get('buscar');
        $monto          = $request->get('monto');
        $soloPendientes = filter_var($request->get('solo_pendientes', true), FILTER_VALIDATE_BOOLEAN);

        $q = DB::table('compras')
            ->leftJoin($this->efectivoPagadoSub(), 'ef.compra_id', '=', 'compras.id')
            ->where('compras.pagado_historico', false)
            ->select(
                'compras.rut_emisor',
                'compras.nombre_emisor',
                DB::raw('COUNT(compras.id) as cantidad_docs'),
                DB::raw('COUNT(CASE WHEN compras.tipo_dte IN (61) THEN 1 END) as cantidad_ncs'),
                // Suma neta: facturas POSITIVO, NCs NEGATIVO
                DB::raw('SUM(CASE WHEN compras.tipo_dte IN (61)
                                  THEN -compras.total
                                  ELSE  compras.total END) as total_neto'),
                DB::raw('SUM(CASE WHEN compras.tipo_dte IN (61)
                                  THEN -COALESCE(ef.monto_pagado_efectivo,0)
                                  ELSE  COALESCE(ef.monto_pagado_efectivo,0) END) as total_pagado'),
                DB::raw('SUM(CASE WHEN compras.tipo_dte IN (61)
                                  THEN -(compras.total - COALESCE(ef.monto_pagado_efectivo,0))
                                  ELSE  (compras.total - COALESCE(ef.monto_pagado_efectivo,0)) END) as total_pendiente'),
                DB::raw("SUM(CASE WHEN compras.nc_revision_estado = 'requiere_revision' THEN 1 ELSE 0 END) as facturas_por_revisar")
            )
            ->groupBy('compras.rut_emisor', 'compras.nombre_emisor');

        if ($desde) $q->where('compras.fecha_emision', '>=', $desde);
        if ($hasta) $q->where('compras.fecha_emision', '<=', $hasta);
        if ($buscar) {
            $q->where(function ($sq) use ($buscar) {
                $sq->where('compras.nombre_emisor', 'like', "%$buscar%")
                   ->orWhere('compras.rut_emisor',  'like', "%$buscar%");
            });
        }
        if ($monto) {
            $montoNum = (int) preg_replace('/[^0-9]/', '', $monto);
            if ($montoNum > 0) $q->where('compras.total', $montoNum);
        }
        if ($soloPendientes) {
            $q->havingRaw('total_pendiente > 0');
        }

        $proveedores = $q->orderByDesc('facturas_por_revisar')
                         ->orderByDesc('total_pendiente')
                         ->get();

        // Totales globales
        $totales = DB::table('compras')
            ->leftJoin($this->efectivoPagadoSub(), 'ef.compra_id', '=', 'compras.id')
            ->where('compras.pagado_historico', false)
            ->when($desde, fn($sq) => $sq->where('compras.fecha_emision', '>=', $desde))
            ->when($hasta, fn($sq) => $sq->where('compras.fecha_emision', '<=', $hasta))
            ->selectRaw("
                COUNT(compras.id) as total_docs,
                COUNT(CASE WHEN compras.tipo_dte IN (61) THEN 1 END) as total_ncs,
                COUNT(DISTINCT compras.rut_emisor) as total_proveedores,
                SUM(CASE WHEN compras.tipo_dte IN (61)
                         THEN -compras.total ELSE compras.total END) as total_monto,
                SUM(CASE WHEN compras.tipo_dte IN (61)
                         THEN -COALESCE(ef.monto_pagado_efectivo,0)
                         ELSE  COALESCE(ef.monto_pagado_efectivo,0) END) as total_pagado,
                SUM(CASE WHEN compras.tipo_dte IN (61)
                         THEN -(compras.total - COALESCE(ef.monto_pagado_efectivo,0))
                         ELSE  (compras.total - COALESCE(ef.monto_pagado_efectivo,0)) END) as total_pendiente,
                SUM(CASE WHEN compras.nc_revision_estado = 'requiere_revision' THEN 1 ELSE 0 END) as facturas_por_revisar
            ")
            ->first();

        return response()->json([
            'proveedores' => $proveedores,
            'totales'     => $totales,
        ]);
    }

    // ── Facturas + NCs de un proveedor ───────────────────────────────────────

    public function facturas(Request $request, string $rut)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $soloPendientes = filter_var($request->get('solo_pendientes', false), FILTER_VALIDATE_BOOLEAN);

        $facturas = DB::table('compras')
            ->leftJoin($this->efectivoPagadoSub(), 'ef.compra_id', '=', 'compras.id')
            ->leftJoin(
                DB::raw('(SELECT nc_id, MIN(factura_id) as factura_aplicada_id FROM compra_nc_aplicacion GROUP BY nc_id) nca'),
                'nca.nc_id', '=', 'compras.id'
            )
            ->where('compras.rut_emisor', $rut)
            ->where('compras.pagado_historico', false)
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
                'compras.nc_referencia_id',
                'compras.nc_revision_estado',
                DB::raw('COALESCE(compras.nc_referencia_id, nca.factura_aplicada_id) as nc_posicion_factura_id'),
                DB::raw('COALESCE(ef.monto_pagado_efectivo, 0) as monto_pagado'),
                DB::raw('CASE WHEN compras.tipo_dte IN (61)
                              THEN -(compras.total - COALESCE(ef.monto_pagado_efectivo,0))
                              ELSE   compras.total - COALESCE(ef.monto_pagado_efectivo,0)
                         END as pendiente'),
                DB::raw('(compras.tipo_dte IN (61)) as es_nc'),
                'compras.categoria'
            )
            ->when($desde, fn($q) => $q->where('compras.fecha_emision', '>=', $desde))
            ->when($hasta, fn($q) => $q->where('compras.fecha_emision', '<=', $hasta))
            ->orderByDesc('compras.fecha_emision')
            ->orderByDesc('compras.id')
            ->get();

        if ($soloPendientes) {
            $facturas = $facturas->filter(fn($f) => abs((float)$f->pendiente) > 0.01)->values();
        }

        return response()->json($facturas);
    }

    // ── Facturas que requieren revisión (alerta global) ───────────────────────

    public function porRevisar()
    {
        $facturas = DB::table('compras as f')
            ->where('f.nc_revision_estado', 'requiere_revision')
            ->where('f.pagado_historico', false)
            ->leftJoin('compras as nc', 'nc.nc_referencia_id', '=', 'f.id')
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) monto_banco FROM compra_movimiento GROUP BY compra_id) pb'),
                'pb.compra_id', '=', 'f.id'
            )
            ->select(
                'f.id', 'f.folio', 'f.tipo_dte', 'f.rut_emisor', 'f.nombre_emisor',
                'f.fecha_emision', 'f.neto', 'f.iva', 'f.total', 'f.pdf_url',
                'f.nc_revision_estado',
                DB::raw('COALESCE(pb.monto_banco, 0) as monto_pagado'),
                DB::raw('f.total - COALESCE(pb.monto_banco, 0) as pendiente_sin_nc'),
                'nc.id as nc_id', 'nc.folio as nc_folio',
                'nc.total as nc_total', 'nc.fecha_emision as nc_fecha'
            )
            ->orderByDesc('f.fecha_emision')
            ->get();

        $count = DB::table('compras')
            ->where('nc_revision_estado', 'requiere_revision')
            ->where('pagado_historico', false)
            ->count();

        return response()->json(['facturas' => $facturas, 'count' => $count]);
    }

    // ── NCs disponibles (sin vincular) del proveedor ──────────────────────────

    public function ncsDisponibles(string $rut)
    {
        // NCs sin nc_referencia_id asignado y con saldo pendiente
        $ncs = DB::table('compras as nc')
            ->leftJoin(
                DB::raw('(SELECT nc_id, SUM(monto) monto_nc FROM compra_nc_aplicacion GROUP BY nc_id) ap'),
                'ap.nc_id', '=', 'nc.id'
            )
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) monto_banco FROM compra_movimiento GROUP BY compra_id) pb'),
                'pb.compra_id', '=', 'nc.id'
            )
            ->where('nc.rut_emisor', $rut)
            ->where('nc.tipo_dte', 61)
            ->where('nc.pagado_historico', false)
            ->whereNull('nc.nc_referencia_id')
            ->select(
                'nc.id', 'nc.folio', 'nc.fecha_emision', 'nc.neto', 'nc.iva', 'nc.total',
                DB::raw('COALESCE(ap.monto_nc, 0) + COALESCE(pb.monto_banco, 0) as monto_usado'),
                DB::raw('nc.total - COALESCE(ap.monto_nc, 0) - COALESCE(pb.monto_banco, 0) as saldo_disponible')
            )
            ->havingRaw('saldo_disponible > 0')
            ->orderByDesc('nc.fecha_emision')
            ->get();

        return response()->json($ncs);
    }

    // ── GET /api/registro-compras ─────────────────────────────────────────────
    // Lista plana de documentos de compra individuales (espejo de registroVentas).

    public function registroCompras(Request $request)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $buscar         = $request->get('buscar');
        $monto          = $request->get('monto');
        $categoria      = $request->get('categoria');
        $soloPendientes = filter_var($request->get('solo_pendientes', false), FILTER_VALIDATE_BOOLEAN);

        $q = DB::table('compras')
            ->leftJoin($this->efectivoPagadoSub(), 'ef.compra_id', '=', 'compras.id')
            ->select(
                'compras.id',
                'compras.folio',
                'compras.tipo_dte',
                'compras.nombre_emisor',
                'compras.rut_emisor',
                'compras.fecha_emision',
                'compras.neto',
                'compras.iva',
                'compras.total',
                'compras.categoria',
                'compras.pdf_url',
                'compras.nc_revision_estado',
                'compras.pagado_historico',
                DB::raw('COALESCE(ef.monto_pagado_efectivo, 0) as monto_pagado'),
                DB::raw('CASE
                              WHEN compras.pagado_historico = 1 AND COALESCE(ef.monto_pagado_efectivo, 0) = 0
                                  THEN 0
                              WHEN compras.tipo_dte IN (61)
                                  THEN -(compras.total - COALESCE(ef.monto_pagado_efectivo,0))
                              ELSE compras.total - COALESCE(ef.monto_pagado_efectivo,0)
                         END as pendiente'),
                DB::raw('(compras.tipo_dte IN (61)) as es_nc')
            )
            ->orderByDesc('compras.fecha_emision')
            ->orderByDesc('compras.id');

        if ($desde)     $q->where('compras.fecha_emision', '>=', $desde);
        if ($hasta)     $q->where('compras.fecha_emision', '<=', $hasta);
        if ($categoria) $q->where('compras.categoria', $categoria);
        if ($buscar) {
            $q->where(function ($sq) use ($buscar) {
                $sq->where('compras.nombre_emisor', 'like', "%$buscar%")
                   ->orWhere('compras.rut_emisor',  'like', "%$buscar%")
                   ->orWhere('compras.folio',        'like', "%$buscar%");
            });
        }
        if ($monto) {
            $montoNum = (int) preg_replace('/[^0-9]/', '', $monto);
            if ($montoNum > 0) $q->where('compras.total', $montoNum);
        }

        $compras = $q->get();

        if ($soloPendientes) {
            $compras = $compras->filter(fn($c) => abs((float) $c->pendiente) > 0.01)->values();
        }

        $totalMonto = $compras->sum(fn($c) => $c->es_nc ? -(float)$c->total : (float)$c->total);

        return response()->json([
            'compras' => $compras,
            'totales' => [
                'total_docs'      => $compras->count(),
                'total_monto'     => $totalMonto,
                'total_pendiente' => $compras->sum(fn($c) => (float) $c->pendiente),
            ],
        ]);
    }
}
