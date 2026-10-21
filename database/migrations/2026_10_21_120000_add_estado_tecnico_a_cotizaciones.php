<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado técnico de un proyecto de Winperfil (qué falta del lado de Winperfil para fabricar):
 *   sin_oferta  → venta parcial: falta crear la oferta con solo lo vendido
 *   provisoria  → se pidió material con medidas aproximadas: falta la pauta definitiva
 *   definitiva  → la oferta corresponde exactamente a lo vendido y a las medidas finales
 * NULL = no aplica / aún no se evalúa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'estado_tecnico')) {
                $table->string('estado_tecnico', 20)->nullable()->after('winperfil_numeros_previos');
            }
            if (!Schema::hasColumn('cotizaciones', 'tecnico_nota')) {
                $table->string('tecnico_nota', 255)->nullable()->after('estado_tecnico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            foreach (['tecnico_nota', 'estado_tecnico'] as $c) {
                if (Schema::hasColumn('cotizaciones', $c)) $table->dropColumn($c);
            }
        });
    }
};
