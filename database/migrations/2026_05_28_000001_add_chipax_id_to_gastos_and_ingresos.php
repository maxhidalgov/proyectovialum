<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            // ID interno de Chipax para deduplicar en reimportaciones
            $table->unsignedBigInteger('chipax_id')->nullable()->unique()->after('id');
            $table->string('chipax_proveedor', 255)->nullable()->after('chipax_id');
        });

        Schema::table('ingresos_manuales', function (Blueprint $table) {
            // OT (nota de venta) de Chipax
            $table->unsignedBigInteger('chipax_id')->nullable()->unique()->after('id');
            $table->unsignedInteger('chipax_folio')->nullable()->after('chipax_id');
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(['chipax_id', 'chipax_proveedor']);
        });
        Schema::table('ingresos_manuales', function (Blueprint $table) {
            $table->dropColumn(['chipax_id', 'chipax_folio']);
        });
    }
};
