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
        Schema::create('boleta_periodo_movimiento', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7);  // YYYY-MM
            $table->foreignId('movimiento_id')->constrained('movimientos_bancarios')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);
            $table->timestamps();
            $table->unique(['periodo', 'movimiento_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boleta_periodo_movimiento');
    }
};
