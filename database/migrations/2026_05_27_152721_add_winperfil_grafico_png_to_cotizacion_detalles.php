<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            // PNG (JPEG base64) pre-renderizado por el browser via canvg (SVG→canvas).
            // Se guarda la primera vez que el usuario abre la cotización en el frontend.
            // Permite que el PDF use la imagen directamente sin conversión browser.
            $table->mediumText('winperfil_grafico_png')->nullable()->after('winperfil_grafico');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn('winperfil_grafico_png');
        });
    }
};
