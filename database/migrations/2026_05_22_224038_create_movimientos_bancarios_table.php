<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->string('cuenta', 30);
            $table->date('fecha_contable');
            $table->date('fecha_valor')->nullable();
            $table->string('descripcion', 255);
            $table->decimal('monto', 15, 2);
            $table->char('tipo', 1);        // C=crédito, D=débito
            $table->string('numero_documento', 50)->nullable();
            $table->decimal('saldo_disponible', 15, 2)->nullable();
            $table->string('bch_codigo', 20)->nullable();
            $table->json('raw')->nullable();
            $table->unsignedBigInteger('compra_id')->nullable();
            $table->unsignedBigInteger('cotizacion_id')->nullable();
            $table->string('categoria', 80)->nullable();
            $table->boolean('conciliado')->default(false);
            $table->timestamps();

            $table->unique(['cuenta', 'fecha_contable', 'numero_documento', 'monto'], 'mov_unique');
            $table->index(['cuenta', 'fecha_contable']);
            $table->index('conciliado');
            $table->foreign('compra_id')->references('id')->on('compras')->nullOnDelete();
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_bancarios');
    }
};
