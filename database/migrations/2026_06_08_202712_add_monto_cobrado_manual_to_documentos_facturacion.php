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
            $table->decimal('monto_cobrado_manual', 14, 2)->nullable()->after('pagado_con_tarjeta');
            $table->string('cobrado_manual_nota', 255)->nullable()->after('monto_cobrado_manual');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->dropColumn(['monto_cobrado_manual', 'cobrado_manual_nota']);
        });
    }
};
