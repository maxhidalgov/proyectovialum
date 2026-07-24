<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('compras', 'stock_recibido_at')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->timestamp('stock_recibido_at')->nullable();
            });

            // Marcar TODAS las compras históricas como ya manejadas, para que no
            // aparezcan como "pendientes de recibir" ni sumen stock retroactivamente
            // (chocaría con el conteo inicial). Solo las compras NUEVAS quedan pendientes.
            DB::table('compras')
                ->whereNull('stock_recibido_at')
                ->update(['stock_recibido_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('compras', 'stock_recibido_at')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->dropColumn('stock_recibido_at');
            });
        }
    }
};
