<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Permite vincular un documento a múltiples transacciones Transbank
// (caso: 2 abonos distintos cubiertos por una sola boleta/factura).
// transaccion_id sigue siendo único (una tx → un doc máximo).

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transbank_factura', function (Blueprint $table) {
            $table->dropUnique(['documento_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transbank_factura', function (Blueprint $table) {
            $table->unique('documento_id');
        });
    }
};
