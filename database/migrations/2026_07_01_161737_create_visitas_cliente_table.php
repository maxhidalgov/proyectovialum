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
        Schema::create('visitas_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->enum('tipo', ['medicion', 'instalacion', 'postventa', 'otro']);
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->enum('estado', ['programada', 'realizada', 'cancelada'])->default('programada');
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('visita_empleado', function (Blueprint $table) {
            $table->foreignId('visita_id')->constrained('visitas_cliente')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->primary(['visita_id', 'empleado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visita_empleado');
        Schema::dropIfExists('visitas_cliente');
    }
};
