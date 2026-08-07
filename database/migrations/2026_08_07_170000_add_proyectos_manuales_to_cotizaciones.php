<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proyectos manuales en el Tablero de Operaciones (Fase 2): permite crear un
 * proyecto sin cotización (como en Monday). Se guarda como una cotización con
 * `es_manual = true` y campos propios (nombre, montos, cant, m2) para reusar todo
 * el tablero (estados, tiempos, pedido proveedor, postventa, agrupación).
 */
return new class extends Migration
{
    public function up(): void
    {
        // cliente_id pasa a nullable: un proyecto manual puede no tener cliente ligado
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_id')->nullable()->change();
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'es_manual')) {
                $table->boolean('es_manual')->default(false)->index();
            }
            if (!Schema::hasColumn('cotizaciones', 'nombre_manual')) {
                $table->string('nombre_manual')->nullable();
            }
            if (!Schema::hasColumn('cotizaciones', 'abono_manual')) {
                $table->decimal('abono_manual', 14, 2)->default(0);
            }
            if (!Schema::hasColumn('cotizaciones', 'cant_manual')) {
                $table->integer('cant_manual')->nullable();
            }
            if (!Schema::hasColumn('cotizaciones', 'm2_manual')) {
                $table->decimal('m2_manual', 10, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            foreach (['es_manual', 'nombre_manual', 'abono_manual', 'cant_manual', 'm2_manual'] as $col) {
                if (Schema::hasColumn('cotizaciones', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
