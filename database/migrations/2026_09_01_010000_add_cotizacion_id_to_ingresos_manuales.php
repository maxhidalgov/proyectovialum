<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ingresos_manuales', 'cotizacion_id')) {
            Schema::table('ingresos_manuales', function (Blueprint $table) {
                $table->unsignedBigInteger('cotizacion_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ingresos_manuales', 'cotizacion_id')) {
            Schema::table('ingresos_manuales', function (Blueprint $table) {
                $table->dropColumn('cotizacion_id');
            });
        }
    }
};
