<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agente_conversaciones')) {
            Schema::create('agente_conversaciones', function (Blueprint $table) {
                $table->id();
                $table->string('canal', 20)->default('simulador');   // simulador | whatsapp
                $table->string('identificador', 60)->nullable();      // teléfono o id de sesión
                $table->string('nombre_contacto', 120)->nullable();
                $table->enum('estado', ['activa', 'calificado', 'derivada', 'cerrada'])->default('activa');
                $table->boolean('bot_activo')->default(true);         // false cuando un humano toma la conversación
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->longText('historial')->nullable();            // array de mensajes Claude (JSON)
                $table->timestamps();
                $table->index(['canal', 'identificador']);
            });
        }

        if (!Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 120)->nullable();
                $table->string('telefono', 40)->nullable();
                $table->string('email', 120)->nullable();
                $table->string('comuna', 80)->nullable();
                $table->string('tipo_producto', 40)->nullable();     // ventanas | puertas | otro
                $table->string('material', 20)->nullable();          // pvc | aluminio | no_sabe
                $table->string('tipo_obra', 40)->nullable();         // casa_nueva | remodelacion | constructora | otro
                $table->text('detalle')->nullable();
                $table->string('presupuesto_aprox', 60)->nullable();
                $table->string('origen', 20)->default('whatsapp');
                $table->enum('estado', ['nuevo', 'contactado', 'convertido', 'descartado'])->default('nuevo');
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->unsignedBigInteger('conversacion_id')->nullable();
                $table->unsignedBigInteger('vendedor_id')->nullable();
                $table->timestamps();
                $table->index('estado');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('agente_conversaciones');
    }
};
