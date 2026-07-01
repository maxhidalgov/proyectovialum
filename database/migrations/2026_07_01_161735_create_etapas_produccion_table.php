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
        Schema::create('etapas_produccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->enum('etapa', [
                'corte_perfiles',
                'corte_vidrio',
                'fabricacion_termopanel',
                'armado',
                'vidriado',
                'junquillos',
                'control',
                'instalacion',
                'entrega',
            ]);
            $table->enum('estado', ['pendiente', 'en_progreso', 'completado'])->default('pendiente');
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin_estimada')->nullable();
            $table->date('fecha_fin_real')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['cotizacion_id', 'etapa']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('etapas_produccion');
    }
};
