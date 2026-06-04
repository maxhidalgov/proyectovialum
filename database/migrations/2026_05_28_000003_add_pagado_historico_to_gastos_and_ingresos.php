<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->boolean('pagado_historico')->default(false)->after('notas');
            $table->date('fecha_pago_historico')->nullable()->after('pagado_historico');
        });

        Schema::table('ingresos_manuales', function (Blueprint $table) {
            $table->boolean('pagado_historico')->default(false)->after('notas');
            $table->date('fecha_pago_historico')->nullable()->after('pagado_historico');
        });

        // Marcar como histórico todo lo de 2024 y antes importado desde Chipax
        // (tienen chipax_id → sabemos que Chipax los tenía conciliados,
        //  pero no tenemos los movimientos bancarios de ese período)
        DB::statement("
            UPDATE gastos
            SET pagado_historico     = 1,
                fecha_pago_historico = fecha
            WHERE chipax_id IS NOT NULL
              AND YEAR(fecha) <= 2024
        ");

        DB::statement("
            UPDATE ingresos_manuales
            SET pagado_historico     = 1,
                fecha_pago_historico = fecha
            WHERE chipax_id IS NOT NULL
              AND YEAR(fecha) <= 2024
        ");
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(['pagado_historico', 'fecha_pago_historico']);
        });
        Schema::table('ingresos_manuales', function (Blueprint $table) {
            $table->dropColumn(['pagado_historico', 'fecha_pago_historico']);
        });
    }
};
