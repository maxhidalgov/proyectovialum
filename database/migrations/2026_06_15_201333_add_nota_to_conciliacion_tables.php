<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->text('nota')->nullable()->after('categoria');
        });
        Schema::table('compra_movimiento', function (Blueprint $table) {
            $table->text('nota')->nullable()->after('monto');
        });
        Schema::table('venta_movimiento', function (Blueprint $table) {
            $table->text('nota')->nullable()->after('monto');
        });
        Schema::table('gasto_movimiento', function (Blueprint $table) {
            $table->text('nota')->nullable()->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->dropColumn('nota');
        });
        Schema::table('compra_movimiento', function (Blueprint $table) {
            $table->dropColumn('nota');
        });
        Schema::table('venta_movimiento', function (Blueprint $table) {
            $table->dropColumn('nota');
        });
        Schema::table('gasto_movimiento', function (Blueprint $table) {
            $table->dropColumn('nota');
        });
    }
};
