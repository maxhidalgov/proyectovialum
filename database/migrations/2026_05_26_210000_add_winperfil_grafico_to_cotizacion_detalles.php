<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            // SVG base64 del gráfico de Winperfil para este ítem
            $table->mediumText('winperfil_grafico')->nullable()->after('pulido');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn('winperfil_grafico');
        });
    }
};
