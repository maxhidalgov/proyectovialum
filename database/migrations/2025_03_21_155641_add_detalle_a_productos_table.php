<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('unidad_medida')->nullable(); // Ej: metro, unidad, kg
            $table->decimal('largo_total', 8, 2)->nullable(); // Largo del producto (ej: 6.00 metros)
            $table->decimal('peso_por_metro', 8, 3)->nullable(); // Peso por metro lineal
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            //
        });
    }
};
