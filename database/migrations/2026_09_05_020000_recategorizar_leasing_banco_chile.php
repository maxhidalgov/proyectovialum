<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Las cuotas de leasing (camión) llegan como facturas de Banco de Chile por
        // ~$443.851 neto (≈ $528.183 bruto) y quedaron categorizadas "Gastos Financieros".
        // No se puede mover por proveedor (Banco de Chile también emite gastos financieros
        // reales), así que se recategorizan por MONTO recurrente de la cuota → "Leasing".
        DB::table('compras')
            ->where('categoria', 'Gastos Financieros')
            ->where('nombre_emisor', 'like', '%Banco de Chile%')
            ->where(function ($q) {
                $q->whereBetween('neto', [443700, 444000])
                  ->orWhereBetween('total', [528000, 528400]);
            })
            ->update(['categoria' => 'Leasing', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('compras')
            ->where('categoria', 'Leasing')
            ->where('nombre_emisor', 'like', '%Banco de Chile%')
            ->update(['categoria' => 'Gastos Financieros', 'updated_at' => now()]);
    }
};
