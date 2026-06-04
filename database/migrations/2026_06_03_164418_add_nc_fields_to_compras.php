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
        Schema::table('compras', function (Blueprint $table) {
            // FK al DTE 33/34 original que esta NC (DTE 61) referencia.
            // Solo se rellena en NCs; el usuario lo asigna manualmente desde CxP.
            $table->unsignedBigInteger('nc_referencia_id')->nullable()->after('pagado_historico');
            $table->foreign('nc_referencia_id')->references('id')->on('compras')->nullOnDelete();

            // Estado de revisión para facturas DTE 33/34 que ya tenían compra_movimiento
            // y luego recibieron una NC vinculada.
            //   NULL               = sin revisión pendiente
            //   requiere_revision  = NC llegó, hay que decidir acción
            //   reembolso_pendiente= se espera devolución bancaria (abono en cuenta)
            //   aplicado           = NC aplicada a futura factura via compra_nc_aplicacion
            //   ignorado           = usuario confirmó que no hay acción pendiente
            $table->enum('nc_revision_estado', [
                'requiere_revision',
                'reembolso_pendiente',
                'aplicado',
                'ignorado',
            ])->nullable()->after('nc_referencia_id');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropForeign(['nc_referencia_id']);
            $table->dropColumn(['nc_referencia_id', 'nc_revision_estado']);
        });
    }
};
