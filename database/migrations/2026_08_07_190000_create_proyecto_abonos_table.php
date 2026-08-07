<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Abonos manuales por proyecto (Tablero de Operaciones). Cada abono es
 * fecha + monto + nota, así el abono total = suma de sus abonos y se puede ver
 * el detalle. Solo para proyectos manuales (es_manual); los no manuales derivan
 * sus abonos de las facturas/conciliación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proyecto_abonos')) {
            Schema::create('proyecto_abonos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
                $table->date('fecha');
                $table->decimal('monto', 14, 2);
                $table->string('nota')->nullable();
                $table->timestamps();
                $table->index('cotizacion_id');
            });
        }

        // Migrar el abono_manual que ya exista a un primer abono con fecha
        if (Schema::hasColumn('cotizaciones', 'abono_manual')) {
            $manuales = DB::table('cotizaciones')
                ->where('es_manual', true)
                ->where('abono_manual', '>', 0)
                ->get(['id', 'abono_manual', 'fecha']);
            foreach ($manuales as $m) {
                $yaTiene = DB::table('proyecto_abonos')->where('cotizacion_id', $m->id)->exists();
                if (!$yaTiene) {
                    DB::table('proyecto_abonos')->insert([
                        'cotizacion_id' => $m->id,
                        'fecha'         => $m->fecha ?: now()->toDateString(),
                        'monto'         => $m->abono_manual,
                        'nota'          => 'Abono inicial',
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_abonos');
    }
};
