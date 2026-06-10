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
        Schema::create('boleta_resumenes', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7);          // YYYY-MM
            $table->string('forma_pago', 30);      // efectivo | tarjeta_credito | tarjeta_debito | transferencia | cheque
            $table->unsignedInteger('total_boletas')->default(0);
            $table->decimal('monto_total', 14, 2)->default(0);
            $table->boolean('conciliado')->default(false);
            $table->timestamps();

            $table->unique(['periodo', 'forma_pago']);
        });

        Schema::create('boleta_resumen_movimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boleta_resumen_id')->constrained('boleta_resumenes')->cascadeOnDelete();
            $table->foreignId('movimiento_id')->constrained('movimientos_bancarios')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);
            $table->timestamps();

            $table->unique(['boleta_resumen_id', 'movimiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boleta_resumen_movimiento');
        Schema::dropIfExists('boleta_resumenes');
    }
};
