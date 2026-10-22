<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de una cotización informados por el bot de WhatsApp: forma de pago, monto, fecha y N° de
 * transacción (voucher Transbank, para cuadrar después). Si el pago NO tiene boleta/factura
 * (cuenta_como_abono = true) va acompañado de una nota de venta (ingresos_manuales) que es la que suma
 * al abono; si tiene documento, queda solo informativo para no contar el abono dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cotizacion_pagos')) {
            return;
        }
        Schema::create('cotizacion_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotizacion_id')->index();
            $table->date('fecha');
            $table->decimal('monto', 14, 2);
            $table->string('forma_pago', 30);
            $table->string('nro_transaccion', 60)->nullable();
            $table->unsignedBigInteger('documento_id')->nullable()->index();
            $table->unsignedBigInteger('ingreso_manual_id')->nullable();
            $table->boolean('cuenta_como_abono')->default(false);
            $table->string('nota')->nullable();
            $table->string('origen', 20)->default('bot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_pagos');
    }
};
