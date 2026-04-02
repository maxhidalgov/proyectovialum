<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insert only if they don't already exist (safe to re-run)
        $existing = DB::table('estados_cotizacion')->pluck('nombre')->toArray();

        if (!in_array('En Producción', $existing)) {
            DB::table('estados_cotizacion')->insert([
                'nombre'     => 'En Producción',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!in_array('Entregada', $existing)) {
            DB::table('estados_cotizacion')->insert([
                'nombre'     => 'Entregada',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('estados_cotizacion')
            ->whereIn('nombre', ['En Producción', 'Entregada'])
            ->delete();
    }
};
