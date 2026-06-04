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
        Schema::create('venta_nc_aplicacion', function (Blueprint $table) {
            $table->id();
            // NC (tipo_documento_bsale_id=2) que provee el crédito
            $table->unsignedBigInteger('nc_id');
            $table->foreign('nc_id')->references('id')->on('documentos_facturacion')->cascadeOnDelete();
            // Factura de venta sobre la que se aplica el crédito
            $table->unsignedBigInteger('factura_id');
            $table->foreign('factura_id')->references('id')->on('documentos_facturacion')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->date('fecha');
            $table->string('nota', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_nc_aplicacion');
    }
};
