<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transbank_factura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaccion_id')
                  ->unique()  // una transacción → un documento máximo
                  ->constrained('transbank_transacciones')
                  ->cascadeOnDelete();
            $table->foreignId('documento_id')
                  ->unique()  // un documento → una transacción máximo
                  ->constrained('documentos_facturacion')
                  ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transbank_factura');
    }
};
