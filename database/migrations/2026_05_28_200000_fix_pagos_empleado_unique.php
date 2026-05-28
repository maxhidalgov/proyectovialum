<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_empleado', function (Blueprint $table) {
            // Primero agregar índice simple en empleado_id para que la FK siga cubierta
            // (MySQL requiere índice en la columna FK antes de poder quitar el composite)
            $table->index('empleado_id', 'pagos_empleado_empleado_id_idx');
        });

        Schema::table('pagos_empleado', function (Blueprint $table) {
            // Ahora sí podemos quitar el composite unique (FK ya está cubierta por el índice simple)
            $table->dropUnique(['empleado_id', 'periodo', 'tipo']);
        });

        // Limpiar duplicados antes de agregar la nueva unique en chipax_remuneracion_id
        // Si ya existe un registro con chipax_remuneracion_id, eliminar el que no tiene
        // (pueden haber duplicados por el bug del import anterior)
        DB::statement("
            DELETE p1 FROM pagos_empleado p1
            INNER JOIN pagos_empleado p2
            ON p1.empleado_id = p2.empleado_id
               AND p1.periodo = p2.periodo
               AND p1.tipo = p2.tipo
               AND p1.id > p2.id
            WHERE p1.chipax_remuneracion_id IS NULL
              AND p2.chipax_remuneracion_id IS NOT NULL
        ");

        Schema::table('pagos_empleado', function (Blueprint $table) {
            // Nueva unique: cada registro de Chipax es único
            $table->unique('chipax_remuneracion_id', 'pagos_empleado_chipax_rem_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_empleado', function (Blueprint $table) {
            $table->dropUnique('pagos_empleado_chipax_rem_unique');
            $table->unique(['empleado_id', 'periodo', 'tipo']);
        });
    }
};
