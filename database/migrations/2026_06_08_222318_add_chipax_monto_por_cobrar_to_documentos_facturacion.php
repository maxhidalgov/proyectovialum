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
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->decimal('chipax_monto_por_cobrar', 14, 2)->nullable()->after('monto_cobrado_manual');
            $table->timestamp('chipax_cobranza_synced_at')->nullable()->after('chipax_monto_por_cobrar');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->dropColumn(['chipax_monto_por_cobrar', 'chipax_cobranza_synced_at']);
        });
    }
};
