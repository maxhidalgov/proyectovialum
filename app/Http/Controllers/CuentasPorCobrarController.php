<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentasPorCobrarController extends Controller
{
    // tipo_documento_bsale_id = 2 → Nota de Crédito de venta
    private const TIPO_NC = 2;

    // Subquery de monto cobrado efectivo por documento de venta:
    // facturas normales: cobrado banco + NC aplicada sobre esta factura
    // NCs (tipo 2)     : cobrado banco + monto de esta NC ya aplicado a alguna factura
    private function efectivoCobradoSub(): \Illuminate\Database\Query\Expression
    {
        return DB::raw("(
            SELECT
                df.id AS df_id,
                CASE
                  WHEN df.chipax_monto_por_cobrar IS NOT NULL
                    THEN df.monto - df.chipax_monto_por_cobrar
                  ELSE
                    COALESCE(vm.monto_cobrado, 0)
                      + COALESCE(tbk.monto_tbk, 0)
                      + COALESCE(df.monto_cobrado_manual, 0)
                      + CASE WHEN df.tipo_documento_bsale_id = 2
                             THEN COALESCE(ncnc.monto_nc, 0)
                             ELSE COALESCE(ncf.monto_nc, 0)
                        END
                END AS monto_cobrado_efectivo
            FROM documentos_facturacion df
            LEFT JOIN (SELECT venta_id, SUM(monto) monto_cobrado FROM venta_movimiento GROUP BY venta_id) vm
                   ON vm.venta_id = df.id
            LEFT JOIN (SELECT tf.documento_id, SUM(tt.monto_original) monto_tbk
                       FROM transbank_factura tf
                       JOIN transbank_transacciones tt ON tt.id = tf.transaccion_id
                       GROUP BY tf.documento_id) tbk
                   ON tbk.documento_id = df.id
            LEFT JOIN (SELECT factura_id AS df_id, SUM(monto) monto_nc FROM venta_nc_aplicacion GROUP BY factura_id) ncf
                   ON ncf.df_id = df.id
            LEFT JOIN (SELECT nc_id AS df_id, SUM(monto) monto_nc FROM venta_nc_aplicacion GROUP BY nc_id) ncnc
                   ON ncnc.df_id = df.id
        ) AS ec");
    }

    // Resumen por cliente
    public function index(Request $request)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $buscar         = $request->get('buscar');
        $soloPendientes = filter_var($request->get('solo_pendientes', true), FILTER_VALIDATE_BOOLEAN);

        $q = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c',  'c.id',      '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_cot', 'cl_cot.id', '=', 'c.cliente_id')
            ->leftJoin('clientes as cl_dir', 'cl_dir.id', '=', 'df.cliente_id')
            ->leftJoin($this->efectivoCobradoSub(), 'ec.df_id', '=', 'df.id')
            ->where('df.estado', 'emitido')
            ->select(
                DB::raw('COALESCE(cl_dir.id, cl_cot.id) as cliente_id'),
                DB::raw('COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre) as razon_social'),
                DB::raw('COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut) as identification'),
                DB::raw('COUNT(df.id) as cantidad_docs'),
                DB::raw('COUNT(CASE WHEN df.tipo_documento_bsale_id = 2 THEN 1 END) as cantidad_ncs'),
                DB::raw('SUM(CASE WHEN df.tipo_documento_bsale_id = 2 THEN -df.monto ELSE df.monto END) as total_facturado'),
                DB::raw('SUM(CASE WHEN df.tipo_documento_bsale_id = 2
                                  THEN -COALESCE(ec.monto_cobrado_efectivo,0)
                                  ELSE  COALESCE(ec.monto_cobrado_efectivo,0) END) as total_cobrado'),
                DB::raw('SUM(CASE WHEN df.tipo_documento_bsale_id = 2
                                  THEN -(df.monto - COALESCE(ec.monto_cobrado_efectivo,0))
                                  ELSE  (df.monto - COALESCE(ec.monto_cobrado_efectivo,0)) END) as total_pendiente'),
                DB::raw("SUM(CASE WHEN df.nc_revision_estado = 'requiere_revision' THEN 1 ELSE 0 END) as facturas_por_revisar")
            )
            ->groupBy(
                DB::raw('COALESCE(cl_dir.id, cl_cot.id)'),
                DB::raw('COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre)'),
                DB::raw('COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut)')
            );

        if ($desde) $q->where('df.fecha_emision', '>=', $desde);
        if ($hasta) $q->where('df.fecha_emision', '<=', $hasta);
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

        $clientes = $q->orderByDesc('facturas_por_revisar')->orderByDesc('total_pendiente')->get();

        $totales = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c',  'c.id',      '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_cot', 'cl_cot.id', '=', 'c.cliente_id')
            ->leftJoin('clientes as cl_dir', 'cl_dir.id', '=', 'df.cliente_id')
            ->leftJoin($this->efectivoCobradoSub(), 'ec.df_id', '=', 'df.id')
            ->where('df.estado', 'emitido')
            ->when($desde, fn($sq) => $sq->where('df.fecha_emision', '>=', $desde))
            ->when($hasta, fn($sq) => $sq->where('df.fecha_emision', '<=', $hasta))
            ->selectRaw("
                COUNT(df.id) as total_docs,
                COUNT(CASE WHEN df.tipo_documento_bsale_id = 2 THEN 1 END) as total_ncs,
                COUNT(DISTINCT COALESCE(cl_dir.id, cl_cot.id)) as total_clientes,
                SUM(CASE WHEN df.tipo_documento_bsale_id = 2 THEN -df.monto ELSE df.monto END) as total_facturado,
                SUM(CASE WHEN df.tipo_documento_bsale_id = 2
                         THEN -COALESCE(ec.monto_cobrado_efectivo,0)
                         ELSE  COALESCE(ec.monto_cobrado_efectivo,0) END) as total_cobrado,
                SUM(CASE WHEN df.tipo_documento_bsale_id = 2
                         THEN -(df.monto - COALESCE(ec.monto_cobrado_efectivo,0))
                         ELSE  (df.monto - COALESCE(ec.monto_cobrado_efectivo,0)) END) as total_pendiente,
                SUM(CASE WHEN df.nc_revision_estado = 'requiere_revision' THEN 1 ELSE 0 END) as facturas_por_revisar
            ")
            ->first();

        return response()->json(['clientes' => $clientes, 'totales' => $totales]);
    }

    // Facturas + NCs de un cliente
    public function facturas(Request $request, string $clienteId)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $soloPendientes = filter_var($request->get('solo_pendientes', false), FILTER_VALIDATE_BOOLEAN);

        $facturas = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c', 'c.id', '=', 'df.cotizacion_id')
            ->leftJoin($this->efectivoCobradoSub(), 'ec.df_id', '=', 'df.id')
            ->where(function ($sq) use ($clienteId) {
                if (is_numeric($clienteId)) {
                    $sq->where('c.cliente_id', (int) $clienteId)
                       ->orWhere('df.cliente_id', (int) $clienteId);
                } else {
                    // Lookup por RUT para clientes sin registro local
                    $sq->where('df.bsale_cliente_rut', $clienteId);
                }
            })
            ->where('df.estado', 'emitido')
            ->select(
                'df.id', 'df.cotizacion_id', 'df.tipo', 'df.monto',
                DB::raw('COALESCE(df.neto, df.monto) as neto'),
                'df.fecha_emision', 'df.numero_documento_bsale',
                'df.url_pdf_bsale', 'df.id_documento_bsale', 'df.tipo_documento_bsale_id',
                'df.nc_referencia_df_id', 'df.nc_revision_estado',
                'df.monto_cobrado_manual', 'df.cobrado_manual_nota',
                DB::raw('COALESCE(ec.monto_cobrado_efectivo, 0) as monto_cobrado'),
                DB::raw('CASE WHEN df.tipo_documento_bsale_id = 2
                              THEN -(df.monto - COALESCE(ec.monto_cobrado_efectivo,0))
                              ELSE   df.monto - COALESCE(ec.monto_cobrado_efectivo,0)
                         END as pendiente'),
                DB::raw('(df.tipo_documento_bsale_id = 2) as es_nc')
            )
            ->when($desde, fn($q) => $q->where('df.fecha_emision', '>=', $desde))
            ->when($hasta, fn($q) => $q->where('df.fecha_emision', '<=', $hasta))
            ->orderByRaw('df.tipo_documento_bsale_id = 2 ASC')
            ->orderByDesc('df.fecha_emision')
            ->get();

        if ($soloPendientes) {
            $facturas = $facturas->filter(fn($f) => abs((float)$f->pendiente) > 0.01)->values();
        }

        return response()->json($facturas);
    }

    // Facturas de venta que requieren revision por NC
    public function porRevisar()
    {
        $facturas = DB::table('documentos_facturacion as df')
            ->where('df.nc_revision_estado', 'requiere_revision')
            ->where('df.estado', 'emitido')
            ->leftJoin('documentos_facturacion as nc', 'nc.nc_referencia_df_id', '=', 'df.id')
            ->leftJoin(
                DB::raw('(SELECT venta_id, SUM(monto) monto_cobrado FROM venta_movimiento GROUP BY venta_id) vm'),
                'vm.venta_id', '=', 'df.id'
            )
            ->leftJoin('clientes as cl', 'cl.id', '=', 'df.cliente_id')
            ->select(
                'df.id', 'df.numero_documento_bsale', 'df.tipo_documento_bsale_id',
                'df.fecha_emision', 'df.monto', 'df.url_pdf_bsale', 'df.nc_revision_estado',
                DB::raw('COALESCE(cl.razon_social, df.bsale_cliente_nombre) as cliente_nombre'),
                DB::raw('COALESCE(cl.identification, df.bsale_cliente_rut) as cliente_rut'),
                DB::raw('COALESCE(vm.monto_cobrado, 0) as monto_cobrado'),
                'nc.id as nc_id', 'nc.numero_documento_bsale as nc_numero',
                'nc.monto as nc_monto', 'nc.fecha_emision as nc_fecha'
            )
            ->orderByDesc('df.fecha_emision')
            ->get();

        $count = DB::table('documentos_facturacion')
            ->where('nc_revision_estado', 'requiere_revision')
            ->where('estado', 'emitido')
            ->count();

        return response()->json(['facturas' => $facturas, 'count' => $count]);
    }

    // PUT /api/cuentas-cobrar/{id}/cobro-manual
    public function marcarCobradoManual(Request $request, int $id)
    {
        $doc = DB::table('documentos_facturacion')->where('id', $id)->first(['id', 'monto']);
        if (!$doc) return response()->json(['error' => 'No encontrado'], 404);

        $monto = $request->input('monto', $doc->monto);
        $nota  = $request->input('nota', 'Marcado manualmente como cobrado');

        DB::table('documentos_facturacion')->where('id', $id)->update([
            'monto_cobrado_manual' => $monto,
            'cobrado_manual_nota'  => $nota,
            'updated_at'           => now(),
        ]);

        return response()->json(['ok' => true, 'monto_cobrado_manual' => $monto]);
    }

    // DELETE /api/cuentas-cobrar/{id}/cobro-manual
    public function desmarcarCobradoManual(int $id)
    {
        DB::table('documentos_facturacion')->where('id', $id)->update([
            'monto_cobrado_manual' => null,
            'cobrado_manual_nota'  => null,
            'updated_at'           => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    // GET /api/registro-ventas — lista plana de todos los documentos de venta
    public function registroVentas(Request $request)
    {
        $desde          = $request->get('desde');
        $hasta          = $request->get('hasta');
        $buscar         = $request->get('buscar');
        $soloPendientes = filter_var($request->get('solo_pendientes', false), FILTER_VALIDATE_BOOLEAN);

        $q = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as c',  'c.id',      '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_dir', 'cl_dir.id', '=', 'df.cliente_id')
            ->leftJoin('clientes as cl_cot', 'cl_cot.id', '=', 'c.cliente_id')
            ->leftJoin($this->efectivoCobradoSub(), 'ec.df_id', '=', 'df.id')
            ->where('df.estado', 'emitido')
            ->select(
                'df.id',
                'df.numero_documento_bsale',
                'df.tipo_documento_bsale_id',
                'df.tipo',
                'df.fecha_emision',
                'df.monto',
                'df.url_pdf_bsale',
                'df.monto_cobrado_manual',
                'df.cobrado_manual_nota',
                'df.chipax_monto_por_cobrar',
                DB::raw('COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre) as razon_social'),
                DB::raw('COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut) as identification'),
                DB::raw('COALESCE(ec.monto_cobrado_efectivo, 0) as monto_cobrado'),
                DB::raw('CASE WHEN df.tipo_documento_bsale_id = 2
                              THEN -(df.monto - COALESCE(ec.monto_cobrado_efectivo,0))
                              ELSE   df.monto - COALESCE(ec.monto_cobrado_efectivo,0)
                         END as pendiente'),
                DB::raw('(df.tipo_documento_bsale_id = 2) as es_nc')
            )
            ->orderByDesc('df.fecha_emision')
            ->orderByDesc('df.id');

        if ($desde)  $q->where('df.fecha_emision', '>=', $desde);
        if ($hasta)  $q->where('df.fecha_emision', '<=', $hasta);
        if ($buscar) {
            $q->where(function ($sq) use ($buscar) {
                $sq->where('cl_dir.razon_social',    'like', "%$buscar%")
                   ->orWhere('cl_cot.razon_social',   'like', "%$buscar%")
                   ->orWhere('df.bsale_cliente_nombre','like', "%$buscar%")
                   ->orWhere('df.numero_documento_bsale','like', "%$buscar%")
                   ->orWhere('cl_dir.identification', 'like', "%$buscar%")
                   ->orWhere('cl_cot.identification', 'like', "%$buscar%")
                   ->orWhere('df.bsale_cliente_rut',  'like', "%$buscar%");
            });
        }

        $documentos = $q->get();

        if ($soloPendientes) {
            $documentos = $documentos->filter(fn($d) => abs((float)$d->pendiente) > 0.01)->values();
        }

        $totales = [
            'total_docs'      => $documentos->count(),
            'total_monto'     => $documentos->sum(fn($d) => $d->es_nc ? -(float)$d->monto : (float)$d->monto),
            'total_pendiente' => $documentos->sum(fn($d) => (float)$d->pendiente),
        ];

        return response()->json(['documentos' => $documentos, 'totales' => $totales]);
    }
}
