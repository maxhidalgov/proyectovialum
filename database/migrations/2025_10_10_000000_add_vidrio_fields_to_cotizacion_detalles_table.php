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
            // Campos para productos tipo vidrio
            $table->boolean('esVidrio')->default(false)->after('total');
            $table->decimal('ancho_mm', 10, 2)->nullable()->after('esVidrio');
            $table->decimal('alto_mm', 10, 2)->nullable()->after('ancho_mm');
            $table->decimal('m2', 10, 4)->nullable()->after('alto_mm');
            $table->boolean('pulido')->default(false)->after('m2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn(['esVidrio', 'ancho_mm', 'alto_mm', 'm2', 'pulido']);
        });
    }
};
