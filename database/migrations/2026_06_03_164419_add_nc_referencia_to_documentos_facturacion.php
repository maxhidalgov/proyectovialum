<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            // FK al documento de venta original que esta NC (tipo_documento_bsale_id=2) referencia.
            $table->unsignedBigInteger('nc_referencia_df_id')->nullable()->after('tipo_documento_bsale_id');
            $table->foreign('nc_referencia_df_id')->references('id')->on('documentos_facturacion')->nullOnDelete();

            // Estado de revisión para facturas de venta ya cobradas que reciben una NC.
            $table->enum('nc_revision_estado', [
                'requiere_revision',
                'reembolso_pendiente',
                'aplicado',
                'ignorado',
            ])->nullable()->after('nc_referencia_df_id');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->dropForeign(['nc_referencia_df_id']);
            $table->dropColumn(['nc_referencia_df_id', 'nc_revision_estado']);
        });
    }
};
