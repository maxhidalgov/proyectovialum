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
        Schema::table('lista_precios', function (Blueprint $table) {
            // Agregar relación directa a color
            $table->unsignedBigInteger('color_id')->nullable()->after('producto_id');
            $table->foreign('color_id')->references('id')->on('colores')->onDelete('set null');
            
            // Agregar proveedor sugerido (el que tiene el costo más alto)
            $table->unsignedBigInteger('proveedor_sugerido_id')->nullable()->after('color_id');
            $table->foreign('proveedor_sugerido_id')->references('id')->on('proveedors')->onDelete('set null');
            
            // Índice único para evitar duplicados de producto+color
            $table->unique(['producto_id', 'color_id'], 'unique_producto_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lista_precios', function (Blueprint $table) {
            $table->dropForeign(['color_id']);
            $table->dropForeign(['proveedor_sugerido_id']);
            $table->dropUnique('unique_producto_color');
            $table->dropColumn(['color_id', 'proveedor_sugerido_id']);
        });
    }
};
