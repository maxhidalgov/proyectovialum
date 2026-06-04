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
        Schema::create('compra_nc_aplicacion', function (Blueprint $table) {
            $table->id();
            // NC (DTE 61) que provee el crédito
            $table->unsignedBigInteger('nc_id');
            $table->foreign('nc_id')->references('id')->on('compras')->cascadeOnDelete();
            // Factura (DTE 33/34) sobre la que se aplica el crédito
            $table->unsignedBigInteger('factura_id');
            $table->foreign('factura_id')->references('id')->on('compras')->cascadeOnDelete();
            // Monto aplicado (puede ser parcial)
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
        Schema::dropIfExists('compra_nc_aplicacion');
    }
};
