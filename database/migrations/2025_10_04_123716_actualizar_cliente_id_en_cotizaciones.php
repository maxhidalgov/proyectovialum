<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actualizar todas las cotizaciones para que cliente_id apunte al ID local en lugar de bsale_id
        DB::statement("
            UPDATE cotizaciones c
            INNER JOIN clientes cl ON c.cliente_id = cl.bsale_id
            SET c.cliente_id = cl.id
            WHERE c.cliente_id IS NOT NULL
        ");
        
        echo "✅ Cotizaciones actualizadas: cliente_id ahora apunta a IDs locales\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir: convertir IDs locales de vuelta a bsale_id
        DB::statement("
            UPDATE cotizaciones c
            INNER JOIN clientes cl ON c.cliente_id = cl.id
            SET c.cliente_id = cl.bsale_id
            WHERE c.cliente_id IS NOT NULL AND cl.bsale_id IS NOT NULL
        ");
    }
};
