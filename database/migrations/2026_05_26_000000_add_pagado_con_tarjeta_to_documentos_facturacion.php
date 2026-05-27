<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->boolean('pagado_con_tarjeta')
                  ->nullable()
                  ->default(null)
                  ->after('nro_comprobante_transbank')
                  ->comment('Confirmado por Bsale payments API: el doc recibió un pago con tarjeta');
        });

        // Docs que ya tienen comprobante → definitivamente pagados con tarjeta
        DB::statement('UPDATE documentos_facturacion SET pagado_con_tarjeta = 1 WHERE nro_comprobante_transbank IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->dropColumn('pagado_con_tarjeta');
        });
    }
};
