<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            // Datetime exacto del movimiento según el banco (para ordenar intradía)
            $table->dateTime('fecha_hora_mov')->nullable()->after('fecha_contable');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->dropColumn('fecha_hora_mov');
        });
    }
};
