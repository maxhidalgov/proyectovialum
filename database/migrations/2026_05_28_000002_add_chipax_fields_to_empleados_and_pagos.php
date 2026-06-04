<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            // ID del empleado en Chipax para matching al importar remuneraciones
            $table->unsignedBigInteger('chipax_id')->nullable()->unique()->after('id');
        });

        Schema::table('pagos_empleado', function (Blueprint $table) {
            // ID de la remuneracion en Chipax para deduplicar reimportaciones
            $table->unsignedBigInteger('chipax_remuneracion_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('chipax_id');
        });
        Schema::table('pagos_empleado', function (Blueprint $table) {
            $table->dropColumn('chipax_remuneracion_id');
        });
    }
};
