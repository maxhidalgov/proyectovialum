<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NUMFACTURA no es único en Winperfil (725 = oferta de Ar Alena y A-725 de PESA), así que
 * el índice único (winperfil_numero, winperfil_serie) impedía importar el segundo. Pasa a
 * índice normal; el emparejamiento por fecha/cliente lo hace WinperfilController.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasIndex('cotizaciones', 'idx_cotizacion_winperfil')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->index(['winperfil_numero', 'winperfil_serie'], 'idx_cotizacion_winperfil');
            });
        }
        if (Schema::hasIndex('cotizaciones', 'uq_cotizacion_winperfil')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->dropUnique('uq_cotizacion_winperfil');
            });
        }
    }

    public function down(): void
    {
        // No se restaura el índice único: reintroduciría el bug de números repetidos.
    }
};
