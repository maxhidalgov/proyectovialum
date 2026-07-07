<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cotizacion_estado_historial')) {
            return;
        }

        Schema::create('cotizacion_estado_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->string('tipo', 20);              // 'produccion' | 'comercial'
            $table->string('estado', 60)->nullable();
            $table->string('estado_anterior', 60)->nullable();
            $table->timestamp('fecha')->useCurrent(); // fecha efectiva del cambio (editable)
            $table->timestamps();

            $table->index(['cotizacion_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_estado_historial');
    }
};
