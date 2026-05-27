<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('rut', 12)->unique();
            $table->string('cargo', 100)->nullable();
            $table->decimal('sueldo_base', 12, 2);
            $table->date('fecha_ingreso');
            $table->date('fecha_egreso')->nullable();
            $table->boolean('activo')->default(true);
            $table->string('banco', 60)->nullable();
            $table->string('cuenta_bancaria', 30)->nullable();
            $table->string('tipo_cuenta', 20)->nullable(); // corriente, vista, ahorro
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('pagos_empleado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('movimiento_id')->nullable(); // vinculo con banco
            $table->date('periodo');           // YYYY-MM-01 (mes del sueldo)
            $table->decimal('monto', 12, 2);
            $table->string('tipo', 30)->default('sueldo'); // sueldo, bono, finiquito
            $table->boolean('pagado')->default(false);
            $table->date('fecha_pago')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
            $table->foreign('movimiento_id')->references('id')->on('movimientos_bancarios')->nullOnDelete();
            $table->unique(['empleado_id', 'periodo', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_empleado');
        Schema::dropIfExists('empleados');
    }
};
