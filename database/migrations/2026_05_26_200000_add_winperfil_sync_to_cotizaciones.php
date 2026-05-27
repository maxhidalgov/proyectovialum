<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Añadir campos de referencia Winperfil a cotizaciones
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->unsignedInteger('winperfil_numero')->nullable()->after('adjunto_winperfil');
            $table->char('winperfil_serie', 1)->nullable()->after('winperfil_numero');
            $table->timestamp('winperfil_synced_at')->nullable()->after('winperfil_serie');

            // Índice único para evitar duplicados al re-sincronizar
            $table->unique(['winperfil_numero', 'winperfil_serie'], 'uq_cotizacion_winperfil');
        });

        // Tabla para pedidos/órdenes de fabricación de Winperfil
        Schema::create('winperfil_pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')
                  ->nullable()
                  ->constrained('cotizaciones')
                  ->onDelete('set null');

            // Identificadores Winperfil
            $table->unsignedInteger('numero_presupuesto');
            $table->char('serie', 1);
            $table->string('codigo_enlace')->nullable();   // CODIGOENLACE
            $table->string('codigo_fase')->nullable();     // CODIGOFASE

            // Datos económicos
            $table->decimal('base', 12, 2)->nullable();
            $table->decimal('iva', 5, 2)->nullable();

            // Estado
            $table->string('estado_general', 50)->nullable(); // ESTADOGENERAL
            $table->string('estado_produccion', 50)->nullable();

            // Raw data completo para consultas futuras
            $table->json('raw_data')->nullable();

            $table->timestamps();

            $table->unique(['numero_presupuesto', 'serie'], 'uq_winperfil_pedido');
            $table->index('cotizacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('winperfil_pedidos');

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropUnique('uq_cotizacion_winperfil');
            $table->dropColumn(['winperfil_numero', 'winperfil_serie', 'winperfil_synced_at']);
        });
    }
};
