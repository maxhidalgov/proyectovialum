<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para el Tablero de Operaciones (estilo Monday):
 *  - material: PVC / Aluminio / Otros (para agrupar el tablero)
 *  - estado_obra: estado de la obra (Obra No Emitida, Emitida, En Ejecución, Terminada)
 *  - postventa: bool (hay postventa pendiente/realizada)
 *  - eett: especificación técnica / color libre (ej. "PVC NEGRO", "NOGAL")
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'material')) {
                $table->string('material')->nullable()->default('PVC');
            }
            if (!Schema::hasColumn('cotizaciones', 'estado_obra')) {
                $table->string('estado_obra')->nullable();
            }
            if (!Schema::hasColumn('cotizaciones', 'postventa')) {
                $table->boolean('postventa')->default(false);
            }
            if (!Schema::hasColumn('cotizaciones', 'eett')) {
                $table->string('eett')->nullable();
            }
        });

        // Backfill: tipo dominante = PVC (reclasificable por fila en el tablero)
        DB::table('cotizaciones')->whereNull('material')->update(['material' => 'PVC']);
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            foreach (['material', 'estado_obra', 'postventa', 'eett'] as $col) {
                if (Schema::hasColumn('cotizaciones', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
