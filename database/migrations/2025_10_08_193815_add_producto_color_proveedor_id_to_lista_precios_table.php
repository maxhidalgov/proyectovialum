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
            $table->unsignedBigInteger('producto_color_proveedor_id')->nullable()->after('producto_id');
            
            // Foreign key
            $table->foreign('producto_color_proveedor_id')
                  ->references('id')
                  ->on('producto_color_proveedor')
                  ->onDelete('cascade');
            
            // Índice
            $table->index('producto_color_proveedor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lista_precios', function (Blueprint $table) {
            $table->dropForeign(['producto_color_proveedor_id']);
            $table->dropIndex(['producto_color_proveedor_id']);
            $table->dropColumn('producto_color_proveedor_id');
        });
    }
};
