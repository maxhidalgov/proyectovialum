<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla principal de ingresos sin documento SII
        Schema::create('ingresos_manuales', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('descripcion')->nullable();
            $table->decimal('monto', 12, 2);
            $table->string('categoria')->default('Ingreso');
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        // Pivot: ingreso_manual ↔ movimiento_bancario
        Schema::create('ingreso_movimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingreso_id')
                  ->constrained('ingresos_manuales')
                  ->onDelete('cascade');
            $table->foreignId('movimiento_id')
                  ->constrained('movimientos_bancarios')
                  ->onDelete('cascade');
            $table->decimal('monto', 12, 2);
            $table->timestamps();
            $table->unique(['ingreso_id', 'movimiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingreso_movimiento');
        Schema::dropIfExists('ingresos_manuales');
    }
};
