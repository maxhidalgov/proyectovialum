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
        Schema::create('compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->string('codigo')->nullable();
            $table->string('nombre');
            $table->decimal('cantidad', 12, 3)->default(1);
            $table->string('unidad')->nullable();
            $table->bigInteger('precio_unitario')->default(0);
            $table->decimal('descuento', 5, 2)->default(0);
            $table->bigInteger('total_linea')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compra_items');
    }
};
