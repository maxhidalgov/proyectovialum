<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->boolean('pedido_proveedor')->default(false)->after('total');
            $table->enum('estado_produccion', [
                'En Espera de Medidas',
                'Lista para Corte',
                'En Fabricación',
                'Fabricadas OK',
                'Instalada',
            ])->nullable()->after('pedido_proveedor');
            $table->date('fecha_entrega')->nullable()->after('estado_produccion');
            $table->text('notas_operaciones')->nullable()->after('fecha_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['pedido_proveedor', 'estado_produccion', 'fecha_entrega', 'notas_operaciones']);
        });
    }
};
