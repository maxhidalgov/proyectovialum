<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una cotización de Winperfil guardaba solo NUMFACTURA, pero lismonta/pedbarra piden
 * (presupuesto, nº de oferta). Se guardan ambos y, además, las ofertas que esta cotización
 * reemplazó ("Cambiar oferta"), para que la sync no las vuelva a crear.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'winperfil_presupuesto')) {
                $table->string('winperfil_presupuesto', 20)->nullable()->after('winperfil_serie');
            }
            if (!Schema::hasColumn('cotizaciones', 'winperfil_oferta')) {
                $table->unsignedTinyInteger('winperfil_oferta')->nullable()->after('winperfil_presupuesto');
            }
            if (!Schema::hasColumn('cotizaciones', 'winperfil_numeros_previos')) {
                $table->json('winperfil_numeros_previos')->nullable()->after('winperfil_oferta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            foreach (['winperfil_numeros_previos', 'winperfil_oferta', 'winperfil_presupuesto'] as $c) {
                if (Schema::hasColumn('cotizaciones', $c)) $table->dropColumn($c);
            }
        });
    }
};
