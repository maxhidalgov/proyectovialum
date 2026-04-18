<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanas', function (Blueprint $table) {
            // Hojas adicionales (bay window / correderas)
            $table->integer('hoja_movil_seleccionada')->nullable()->after('hojas_moviles');
            $table->boolean('hoja1_al_frente')->nullable()->after('hoja_movil_seleccionada');

            // Bay Window: anchos por sección
            $table->integer('ancho_izquierda')->nullable()->after('hoja1_al_frente');
            $table->integer('ancho_centro')->nullable()->after('ancho_izquierda');
            $table->integer('ancho_derecha')->nullable()->after('ancho_centro');

            // Bay Window: configuración de tipo por sección (JSON)
            $table->json('tipo_ventana_izquierda')->nullable()->after('ancho_derecha');
            $table->json('tipo_ventana_centro')->nullable()->after('tipo_ventana_izquierda');
            $table->json('tipo_ventana_derecha')->nullable()->after('tipo_ventana_centro');
        });
    }

    public function down(): void
    {
        Schema::table('ventanas', function (Blueprint $table) {
            $table->dropColumn([
                'hoja_movil_seleccionada',
                'hoja1_al_frente',
                'ancho_izquierda',
                'ancho_centro',
                'ancho_derecha',
                'tipo_ventana_izquierda',
                'tipo_ventana_centro',
                'tipo_ventana_derecha',
            ]);
        });
    }
};
