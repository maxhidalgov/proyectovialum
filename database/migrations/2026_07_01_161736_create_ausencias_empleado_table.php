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
        Schema::create('ausencias_empleado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha');
            $table->enum('tipo', ['dia_completo', 'media_manana', 'media_tarde', 'llegada_tarde']);
            $table->text('motivo')->nullable();
            $table->string('workera_permission_id', 50)->nullable();
            $table->timestamps();

            $table->unique(['empleado_id', 'fecha', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ausencias_empleado');
    }
};
