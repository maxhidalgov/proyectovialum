<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_conciliacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('patron', 200);          // texto a buscar en descripcion (case-insensitive)
            $table->string('categoria', 80);
            $table->char('tipo', 1)->default('A'); // C=crédito, D=débito, A=ambos
            $table->unsignedSmallInteger('prioridad')->default(100);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['tipo', 'activa', 'prioridad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_conciliacion');
    }
};
