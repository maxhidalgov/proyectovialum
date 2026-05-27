<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hacer cotizacion_id nullable (mantenemos el FK, solo quitamos NOT NULL)
        DB::statement('ALTER TABLE documentos_facturacion MODIFY cotizacion_id BIGINT UNSIGNED NULL');

        Schema::table('documentos_facturacion', function (Blueprint $table) {
            // FK directa al cliente (para docs sincronizados desde Bsale sin cotización)
            $table->foreignId('cliente_id')
                  ->nullable()
                  ->after('cotizacion_id')
                  ->constrained('clientes')
                  ->onDelete('set null');

            // Info del cliente tal como viene de Bsale (para los que no están en clientes)
            $table->string('bsale_cliente_rut', 30)->nullable()->after('cliente_id');
            $table->string('bsale_cliente_nombre', 255)->nullable()->after('bsale_cliente_rut');

            // Monto neto (sin IVA) — importante para EERR y CxC
            $table->bigInteger('neto')->nullable()->after('monto');

            // ID del tipo de documento en Bsale (1=boleta, 6=factura, etc.)
            $table->integer('tipo_documento_bsale_id')->nullable()->after('neto');
        });
    }

    public function down(): void
    {
        // Revertir nullable
        DB::statement('ALTER TABLE documentos_facturacion MODIFY cotizacion_id BIGINT UNSIGNED NOT NULL');

        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn([
                'cliente_id',
                'bsale_cliente_rut',
                'bsale_cliente_nombre',
                'neto',
                'tipo_documento_bsale_id',
            ]);
        });
    }
};
