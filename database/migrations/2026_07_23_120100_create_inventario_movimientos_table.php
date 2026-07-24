<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventario_movimientos')) {
            Schema::create('inventario_movimientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('producto_id');
                $table->unsignedBigInteger('color_id')->nullable();
                $table->decimal('cantidad', 12, 2); // +entra / -sale
                $table->string('tipo', 30);         // ajuste_inicial|ajuste|compra|venta|merma|consumo_produccion
                $table->string('referencia_tipo', 40)->nullable();
                $table->unsignedBigInteger('referencia_id')->nullable();
                $table->string('nota', 255)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['producto_id', 'color_id']);
                $table->index('tipo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
    }
};
