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
        Schema::create('ia_mensajes', function (Blueprint $table) {
            $table->id();
            $table->enum('rol', ['user', 'assistant']);
            $table->text('contenido');
            $table->json('acciones_ejecutadas')->nullable();
            $table->enum('origen', ['app', 'whatsapp'])->default('app');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ia_mensajes');
    }
};
