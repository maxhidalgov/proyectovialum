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
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            // Agregar campo tipo_item para diferenciar entre ventana y producto
            $table->enum('tipo_item', ['ventana', 'producto'])->default('ventana');
            
            // Agregar referencias a productos
            $table->unsignedBigInteger('producto_lista_id')->nullable();
            $table->unsignedBigInteger('lista_precio_id')->nullable();
            
            // Foreign keys
            $table->foreign('producto_lista_id')->references('id')->on('productos')->onDelete('cascade');
            $table->foreign('lista_precio_id')->references('id')->on('lista_precios')->onDelete('set null');
            
            // Índices
            $table->index('tipo_item');
            $table->index('producto_lista_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            // Eliminar foreign keys primero
            $table->dropForeign(['producto_lista_id']);
            $table->dropForeign(['lista_precio_id']);
            
            // Eliminar columnas
            $table->dropColumn(['tipo_item', 'producto_lista_id', 'lista_precio_id']);
        });
    }
};
