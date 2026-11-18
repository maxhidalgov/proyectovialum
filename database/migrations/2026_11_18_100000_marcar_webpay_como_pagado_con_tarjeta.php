<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Webpay se trataba como un medio distinto de tarjeta: no marcaba pagado_con_tarjeta y por eso las facturas
// pagadas así no aparecían en la conciliación mensual de Transbank. Desde ahora Webpay cuenta como tarjeta
// (y pide voucher); esto corrige los documentos que ya se emitieron como webpay.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('documentos_facturacion') || !Schema::hasColumn('documentos_facturacion', 'pagado_con_tarjeta')) return;

        DB::table('documentos_facturacion')
            ->where('forma_pago', 'webpay')
            ->where(fn ($q) => $q->where('pagado_con_tarjeta', 0)->orWhereNull('pagado_con_tarjeta'))
            ->update(['pagado_con_tarjeta' => 1]);
    }

    public function down(): void
    {
        // sin reversa: es solo la corrección de un dato
    }
};
