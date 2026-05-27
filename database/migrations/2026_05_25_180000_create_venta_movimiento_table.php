<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_movimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('documentos_facturacion')->onDelete('cascade');
            $table->foreignId('movimiento_id')->constrained('movimientos_bancarios')->onDelete('cascade');
            $table->decimal('monto', 12, 2);
            $table->unique(['venta_id', 'movimiento_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_movimiento');
    }
};
