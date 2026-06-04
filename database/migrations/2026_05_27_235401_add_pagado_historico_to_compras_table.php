<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            // Marca facturas pagadas antes de que el sistema empezara a registrar
            // movimientos bancarios (historial pre-migración).
            // Si true → no aparece en Cuentas por Pagar como deuda pendiente.
            $table->boolean('pagado_historico')->default(false)->after('estado');
            $table->date('fecha_pago_historico')->nullable()->after('pagado_historico');
            $table->string('nota_historico', 255)->nullable()->after('fecha_pago_historico');
        });

        // Marcar todo lo de 2024 y anteriores como histórico pagado.
        // Tienen más de 1 año: si no están conciliadas es porque el sistema
        // no tenía movimientos bancarios, no porque realmente estén impagas.
        DB::statement("
            UPDATE compras
            SET pagado_historico     = 1,
                fecha_pago_historico = fecha_emision,
                nota_historico       = 'Historial pre-conciliación (anterior a 2025)'
            WHERE YEAR(fecha_emision) <= 2024
        ");
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn(['pagado_historico', 'fecha_pago_historico', 'nota_historico']);
        });
    }
};
