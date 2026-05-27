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
        Schema::create('compra_movimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('movimiento_id')->constrained('movimientos_bancarios')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->timestamps();
            $table->unique(['compra_id', 'movimiento_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compra_movimiento');
    }
};
