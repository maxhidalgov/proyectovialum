<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordatorios', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->enum('tipo', ['llamada', 'reunion', 'tarea', 'pago', 'seguimiento', 'entrega', 'otro'])->default('tarea');
            $table->enum('estado', ['pendiente', 'completado', 'cancelado'])->default('pendiente');
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->enum('origen', ['app', 'ia', 'workera'])->default('app');
            $table->timestamp('completado_at')->nullable();
            $table->timestamps();

            $table->index(['fecha', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios');
    }
};
