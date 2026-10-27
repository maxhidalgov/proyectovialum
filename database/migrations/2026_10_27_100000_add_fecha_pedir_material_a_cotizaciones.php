<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Día límite para pedir el material de una obra al proveedor. El bot de WhatsApp lo usa para avisar
 * (resumen de la mañana y aviso de la tarde anterior) mientras `pedido_proveedor` siga en falso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'fecha_pedir_material')) {
                $table->date('fecha_pedir_material')->nullable()->after('pedido_proveedor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('cotizaciones', 'fecha_pedir_material')) {
                $table->dropColumn('fecha_pedir_material');
            }
        });
    }
};
