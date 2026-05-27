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
        Schema::create('gasto_movimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gasto_id')->constrained('gastos')->cascadeOnDelete();
            $table->foreignId('movimiento_id')->constrained('movimientos_bancarios')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->unique(['gasto_id', 'movimiento_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gasto_movimiento');
    }
};
