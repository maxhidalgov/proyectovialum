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
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'winperfil_precio_lock')) {
                // Cuando es true, la sync de Winperfil no sobreescribe el total ni las líneas
                // (el precio fue ajustado manualmente y acordado con el cliente).
                $table->boolean('winperfil_precio_lock')->default(false)->after('winperfil_synced_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('cotizaciones', 'winperfil_precio_lock')) {
                $table->dropColumn('winperfil_precio_lock');
            }
        });
    }
};
