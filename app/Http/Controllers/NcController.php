<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona el flujo de Notas de Crédito:
 *
 * COMPRAS (DTE 61 → afectan facturas DTE 33/34):
 *   POST   /api/nc/compra/{nc_id}/vincular          → asigna nc_referencia_id
 *   DELETE /api/nc/compra/{nc_id}/vincular          → quita nc_referencia_id
 *   POST   /api/nc/compra/{nc_id}/aplicar           → crea compra_nc_aplicacion (Escenario B)
 *   DELETE /api/nc/compra/aplicacion/{id}           → elimina aplicacion
 *   PATCH  /api/nc/compra/factura/{id}/estado       → cambia nc_revision_estado
 *   GET    /api/nc/compra/badge                     → count facturas_por_revisar global
 *
 * VENTAS (tipo 2 Bsale → afectan documentos_facturacion):
 *   POST   /api/nc/venta/{nc_id}/vincular
 *   DELETE /api/nc/venta/{nc_id}/vincular
 *   POST   /api/nc/venta/{nc_id}/aplicar
 *   DELETE /api/nc/venta/aplicacion/{id}
 *   PATCH  /api/nc/venta/factura/{id}/estado
 *   GET    /api/nc/venta/badge
 */
class NcController extends Controller
{
    // ════════════════════════════════════════════════════════════════════════
    //  COMPRAS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Vincula una NC (DTE 61) a la factura original (DTE 33/34).
     * Si la factura ya tiene pagos (compra_movimiento), la marca 'requiere_revision'.
     */
    public function vincularCompra(Request $request, int $ncId)
    {
        $request->validate([
            'factura_id' => 'required|integer|exists:compras,id',
        ]);

        $nc = DB::table('compras')->where('id', $ncId)->where('tipo_dte', 61)->firstOrFail();
        $facturaId = (int) $request->input('factura_id');

        DB::table('compras')->where('id', $ncId)->update([
            'nc_referencia_id' => $facturaId,
            'updated_at'       => now(),
        ]);

        // ¿La factura ya tiene pagos? → necesita revisión
        $tienePagos = DB::table('compra_movimiento')->where('compra_id', $facturaId)->exists();
        if ($tienePagos) {
            DB::table('compras')
                ->where('id', $facturaId)
                ->whereNull('nc_revision_estado') // no sobreescribir estados manuales
                ->update(['nc_revision_estado' => 'requiere_revision', 'updated_at' => now()]);
        }

        return response()->json(['ok' => true, 'requiere_revision' => $tienePagos]);
    }

    /**
     * Quita el vínculo NC → factura (y limpia el estado si no quedan más NCs vinculadas).
     */
    public function desvincularCompra(int $ncId)
    {
        $nc = DB::table('compras')->where('id', $ncId)->where('tipo_dte', 61)->firstOrFail();
        $facturaId = $nc->nc_referencia_id;

        DB::table('compras')->where('id', $ncId)->update([
            'nc_referencia_id' => null,
            'updated_at'       => now(),
        ]);

        if ($facturaId) {
            // Si ya no quedan NCs vinculadas → limpiar estado de revisión
            $quedan = DB::table('compras')
                ->where('nc_referencia_id', $facturaId)
                ->where('id', '<>', $ncId)
                ->exists();

            if (!$quedan) {
                DB::table('compras')
                    ->where('id', $facturaId)
                    ->where('nc_revision_estado', 'requiere_revision')
                    ->update(['nc_revision_estado' => null, 'updated_at' => now()]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Aplica el saldo de una NC a una factura sin movimiento bancario (Escenario B).
     * Crea registro en compra_nc_aplicacion.
     * Actualiza nc_revision_estado en la factura si corresponde.
     */
    public function aplicarCompra(Request $request, int $ncId)
    {
        $request->validate([
            'factura_id' => 'required|integer|exists:compras,id',
            'monto'      => 'required|numeric|min:1',
            'fecha'      => 'required|date',
            'nota'       => 'nullable|string|max:500',
        ]);

        $nc        = DB::table('compras')->where('id', $ncId)->where('tipo_dte', 61)->firstOrFail();
        $facturaId = (int) $request->input('factura_id');
        $monto     = (float) $request->input('monto');

        // Validar que la NC tenga saldo suficiente
        $aplicado = (float) DB::table('compra_nc_aplicacion')->where('nc_id', $ncId)->sum('monto');
        $cobrado  = (float) DB::table('compra_movimiento')->where('compra_id', $ncId)->sum('monto');
        $disponible = (float) $nc->total - $aplicado - $cobrado;

        if ($monto > $disponible + 0.01) {
            return response()->json([
                'message' => "La NC solo tiene saldo disponible de " . number_format($disponible, 0, ',', '.'),
            ], 422);
        }

        $id = DB::table('compra_nc_aplicacion')->insertGetId([
            'nc_id'      => $ncId,
            'factura_id' => $facturaId,
            'monto'      => $monto,
            'fecha'      => $request->input('fecha'),
            'nota'       => $request->input('nota'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Si la NC estaba vinculada a esta factura y la factura queda con saldo ~0 → marcar 'aplicado'
        if ($nc->nc_referencia_id === $facturaId) {
            $pagadoBanco = (float) DB::table('compra_movimiento')->where('compra_id', $facturaId)->sum('monto');
            $ncAplicadaTotal = (float) DB::table('compra_nc_aplicacion')->where('factura_id', $facturaId)->sum('monto');
            $factura = DB::table('compras')->where('id', $facturaId)->first(['total']);
            if (($pagadoBanco + $ncAplicadaTotal) >= (float) $factura->total - 0.01) {
                DB::table('compras')->where('id', $facturaId)
                    ->update(['nc_revision_estado' => 'aplicado', 'updated_at' => now()]);
            }
        }

        return response()->json(['ok' => true, 'id' => $id]);
    }

    /**
     * Elimina una aplicación NC (permite corregir errores).
     */
    public function eliminarAplicacionCompra(int $aplicacionId)
    {
        $ap = DB::table('compra_nc_aplicacion')->where('id', $aplicacionId)->firstOrFail();
        DB::table('compra_nc_aplicacion')->where('id', $aplicacionId)->delete();

        // Revertir estado de revisión si corresponde
        $nc = DB::table('compras')->where('id', $ap->nc_id)->first();
        if ($nc && $nc->nc_referencia_id == $ap->factura_id) {
            DB::table('compras')
                ->where('id', $ap->factura_id)
                ->where('nc_revision_estado', 'aplicado')
                ->update(['nc_revision_estado' => 'requiere_revision', 'updated_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Cambia el nc_revision_estado de una factura DTE 33/34.
     * Estados posibles: requiere_revision, reembolso_pendiente, aplicado, ignorado, null
     */
    public function estadoFacturaCompra(Request $request, int $facturaId)
    {
        $request->validate([
            'estado' => 'nullable|in:requiere_revision,reembolso_pendiente,aplicado,ignorado',
        ]);

        DB::table('compras')
            ->where('id', $facturaId)
            ->whereNotIn('tipo_dte', [61]) // solo facturas, no NCs
            ->update(['nc_revision_estado' => $request->input('estado'), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * Badge global: cuántas facturas de compra requieren revisión.
     */
    public function badgeCompra()
    {
        $count = DB::table('compras')
            ->where('nc_revision_estado', 'requiere_revision')
            ->where('pagado_historico', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  VENTAS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Vincula una NC de venta (tipo_documento_bsale_id=2) a la factura original.
     */
    public function vincularVenta(Request $request, int $ncId)
    {
        $request->validate([
            'factura_id' => 'required|integer|exists:documentos_facturacion,id',
        ]);

        $nc = DB::table('documentos_facturacion')
            ->where('id', $ncId)
            ->where('tipo_documento_bsale_id', 2)
            ->firstOrFail();

        $facturaId = (int) $request->input('factura_id');

        DB::table('documentos_facturacion')->where('id', $ncId)->update([
            'nc_referencia_df_id' => $facturaId,
            'updated_at'          => now(),
        ]);

        $tieneCobros = DB::table('venta_movimiento')->where('venta_id', $facturaId)->exists();
        if ($tieneCobros) {
            DB::table('documentos_facturacion')
                ->where('id', $facturaId)
                ->whereNull('nc_revision_estado')
                ->update(['nc_revision_estado' => 'requiere_revision', 'updated_at' => now()]);
        }

        return response()->json(['ok' => true, 'requiere_revision' => $tieneCobros]);
    }

    /**
     * Quita el vínculo NC de venta → factura.
     */
    public function desvincularVenta(int $ncId)
    {
        $nc = DB::table('documentos_facturacion')
            ->where('id', $ncId)
            ->where('tipo_documento_bsale_id', 2)
            ->firstOrFail();

        $facturaId = $nc->nc_referencia_df_id;

        DB::table('documentos_facturacion')->where('id', $ncId)->update([
            'nc_referencia_df_id' => null,
            'updated_at'          => now(),
        ]);

        if ($facturaId) {
            $quedan = DB::table('documentos_facturacion')
                ->where('nc_referencia_df_id', $facturaId)
                ->where('id', '<>', $ncId)
                ->exists();

            if (!$quedan) {
                DB::table('documentos_facturacion')
                    ->where('id', $facturaId)
                    ->where('nc_revision_estado', 'requiere_revision')
                    ->update(['nc_revision_estado' => null, 'updated_at' => now()]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Aplica el saldo de una NC de venta a una factura de venta (sin movimiento bancario).
     */
    public function aplicarVenta(Request $request, int $ncId)
    {
        $request->validate([
            'factura_id' => 'required|integer|exists:documentos_facturacion,id',
            'monto'      => 'required|numeric|min:1',
            'fecha'      => 'required|date',
            'nota'       => 'nullable|string|max:500',
        ]);

        $nc = DB::table('documentos_facturacion')
            ->where('id', $ncId)
            ->where('tipo_documento_bsale_id', 2)
            ->firstOrFail();

        $monto    = (float) $request->input('monto');
        $aplicado = (float) DB::table('venta_nc_aplicacion')->where('nc_id', $ncId)->sum('monto');
        $cobrado  = (float) DB::table('venta_movimiento')->where('venta_id', $ncId)->sum('monto');
        $disponible = (float) $nc->monto - $aplicado - $cobrado;

        if ($monto > $disponible + 0.01) {
            return response()->json([
                'message' => "La NC solo tiene saldo disponible de " . number_format($disponible, 0, ',', '.'),
            ], 422);
        }

        $id = DB::table('venta_nc_aplicacion')->insertGetId([
            'nc_id'      => $ncId,
            'factura_id' => (int) $request->input('factura_id'),
            'monto'      => $monto,
            'fecha'      => $request->input('fecha'),
            'nota'       => $request->input('nota'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    }

    /**
     * Elimina una aplicación NC de venta.
     */
    public function eliminarAplicacionVenta(int $aplicacionId)
    {
        DB::table('venta_nc_aplicacion')->where('id', $aplicacionId)->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Cambia el nc_revision_estado de una factura de venta.
     */
    public function estadoFacturaVenta(Request $request, int $facturaId)
    {
        $request->validate([
            'estado' => 'nullable|in:requiere_revision,reembolso_pendiente,aplicado,ignorado',
        ]);

        DB::table('documentos_facturacion')
            ->where('id', $facturaId)
            ->where('tipo_documento_bsale_id', '<>', 2)
            ->update(['nc_revision_estado' => $request->input('estado'), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * Badge global: cuántas facturas de venta requieren revisión.
     */
    public function badgeVenta()
    {
        $count = DB::table('documentos_facturacion')
            ->where('nc_revision_estado', 'requiere_revision')
            ->where('estado', 'emitido')
            ->count();

        return response()->json(['count' => $count]);
    }
}
