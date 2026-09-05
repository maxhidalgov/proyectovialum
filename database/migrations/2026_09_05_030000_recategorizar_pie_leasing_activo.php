<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // El "pie" del leasing del vehículo nuevo (factura Banco de Chile, folio 48520989,
        // ~$9.013.762) es inversión/compra de activo, NO gasto. Estaba en "Gastos Financieros".
        // Se recategoriza a "Compra de activos" (queda fuera del EERR).
        DB::table('compras')
            ->where('nombre_emisor', 'like', '%Banco de Chile%')
            ->where(function ($q) {
                $q->where('folio', '48520989')
                  ->orWhereBetween('total', [9000000, 9030000]);
            })
            ->whereIn('categoria', ['Gastos Financieros'])
            ->update(['categoria' => 'Compra de activos', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('compras')
            ->where('categoria', 'Compra de activos')
            ->where('nombre_emisor', 'like', '%Banco de Chile%')
            ->update(['categoria' => 'Gastos Financieros', 'updated_at' => now()]);
    }
};
