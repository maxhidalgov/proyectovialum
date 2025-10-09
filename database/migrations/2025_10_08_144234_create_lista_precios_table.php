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
        Schema::create('lista_precios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->decimal('precio_costo', 12, 2)->default(0);
            $table->decimal('margen', 5, 2)->default(0)->comment('Porcentaje de margen');
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Foreign key
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
            
            // Índices
            $table->index('producto_id');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lista_precios');
    }
};
