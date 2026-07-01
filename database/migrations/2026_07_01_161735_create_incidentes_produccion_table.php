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
        Schema::create('incidentes_produccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            $table->text('descripcion');
            $table->enum('tipo', ['rotura_vidrio', 'retraso', 'material_faltante', 'instalacion', 'otro']);
            $table->enum('estado', ['abierto', 'en_resolucion', 'resuelto'])->default('abierto');
            $table->text('accion_requerida')->nullable();
            $table->foreignId('empleado_responsable_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->date('fecha_limite_resolucion')->nullable();
            $table->date('fecha_resuelto')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidentes_produccion');
    }
};
