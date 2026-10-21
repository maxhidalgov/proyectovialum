<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro del último envío de una boleta/factura al cliente (correo con PDF adjunto o
 * PDF compartido por WhatsApp), para mostrar en Boletas/Facturas que ya se envió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            if (!Schema::hasColumn('documentos_facturacion', 'ultimo_envio_via')) {
                $table->string('ultimo_envio_via', 20)->nullable();
            }
            if (!Schema::hasColumn('documentos_facturacion', 'ultimo_envio_a')) {
                $table->string('ultimo_envio_a', 150)->nullable();
            }
            if (!Schema::hasColumn('documentos_facturacion', 'ultimo_envio_at')) {
                $table->timestamp('ultimo_envio_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            foreach (['ultimo_envio_at', 'ultimo_envio_a', 'ultimo_envio_via'] as $c) {
                if (Schema::hasColumn('documentos_facturacion', $c)) $table->dropColumn($c);
            }
        });
    }
};
