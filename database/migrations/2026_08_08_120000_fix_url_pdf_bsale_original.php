<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los PDF de Bsale guardados con `?sfd=99` son el formato TÉRMICO (voucher 80mm)
 * que acorta los nombres largos. Quitando el query se obtiene el PDF ORIGINAL
 * (formato completo A4). Solo aplica a las URLs nuevas de app2.bsale.cl/view.
 * Idempotente (tras correr, ya no hay `?sfd=` que reemplazar).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['documentos_facturacion', 'cotizaciones'] as $tabla) {
            DB::table($tabla)
                ->where('url_pdf_bsale', 'like', '%app2.bsale.cl/view/%?sfd=%')
                ->update(['url_pdf_bsale' => DB::raw("SUBSTRING_INDEX(url_pdf_bsale, '?', 1)")]);
        }
    }

    public function down(): void
    {
        // No reversible (no guardamos el sfd original). No-op.
    }
};
