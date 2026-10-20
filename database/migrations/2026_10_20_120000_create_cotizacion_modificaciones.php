<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de modificaciones de una cotización ya aprobada: el cliente cambia o agrega algo y
 * el total cambia (cambio de oferta en Winperfil o ajuste manual de precio). Guarda el total
 * antes/después para explicar el saldo en Facturación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cotizacion_modificaciones')) {
            return;
        }
        Schema::create('cotizacion_modificaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotizacion_id')->index();
            $table->string('tipo', 20);                              // cambio_oferta | ajuste_precio
            $table->string('oferta_anterior', 20)->nullable();
            $table->string('oferta_nueva', 20)->nullable();
            $table->decimal('total_anterior', 14, 2)->nullable();    // NETO
            $table->decimal('total_nuevo', 14, 2)->nullable();       // NETO
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_modificaciones');
    }
};
