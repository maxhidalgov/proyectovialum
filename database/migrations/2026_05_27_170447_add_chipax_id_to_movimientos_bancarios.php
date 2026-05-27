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
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            // ID de Chipax para deduplicación segura (evita depender del unique compuesto)
            $table->unsignedBigInteger('chipax_id')->nullable()->unique()->after('id');
            // ID de la cuenta corriente en Chipax (para filtrar por banco)
            $table->unsignedInteger('chipax_cuenta_id')->nullable()->after('chipax_id');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->dropUnique(['chipax_id']);
            $table->dropColumn(['chipax_id', 'chipax_cuenta_id']);
        });
    }
};
