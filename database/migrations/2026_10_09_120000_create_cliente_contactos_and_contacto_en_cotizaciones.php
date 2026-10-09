<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contactos por cliente: una empresa (cliente) puede tener varias personas a las que
 * se dirigen las cotizaciones (ej. cliente "Guindo Santo", cotización para "Juanito Pérez").
 * La cotización guarda el contacto elegido (id) y una copia de su nombre (contacto_nombre)
 * para que el PDF no cambie si luego se edita o se elimina el contacto.
 *
 * Idempotente: Railway puede correrla sobre una base que ya tiene parte de los cambios.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cliente_contactos')) {
            Schema::create('cliente_contactos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cliente_id')->index();
                $table->string('nombre', 150);
                $table->string('cargo', 100)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->string('email', 150)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'contacto_id')) {
                $table->unsignedBigInteger('contacto_id')->nullable()->after('cliente_facturacion_id');
            }
            if (!Schema::hasColumn('cotizaciones', 'contacto_nombre')) {
                $table->string('contacto_nombre', 150)->nullable()->after('contacto_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('cotizaciones', 'contacto_nombre')) $table->dropColumn('contacto_nombre');
            if (Schema::hasColumn('cotizaciones', 'contacto_id'))     $table->dropColumn('contacto_id');
        });
        Schema::dropIfExists('cliente_contactos');
    }
};
