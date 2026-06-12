<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingresos_manuales', function (Blueprint $table) {
            $table->foreignId('transbank_transaccion_id')
                  ->nullable()
                  ->after('chipax_folio')
                  ->constrained('transbank_transacciones')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ingresos_manuales', function (Blueprint $table) {
            $table->dropForeign(['transbank_transaccion_id']);
            $table->dropColumn('transbank_transaccion_id');
        });
    }
};
