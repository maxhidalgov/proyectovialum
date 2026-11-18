<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// CotizacionController::store() no sumaba los ítems libres al total, así que las cotizaciones hechas solo con
// ítems libres quedaron con total = 0 (el PDF sí mostraba el monto porque lo suma desde las líneas).
// Se recalcula el total (neto) de esas cotizaciones desde sus líneas. Idempotente: solo toca las que están en 0.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cotizaciones') || !Schema::hasTable('cotizacion_detalles')) return;

        $ids = DB::table('cotizaciones as c')
            ->where('c.total', 0)
            ->where(fn ($q) => $q->where('c.es_manual', 0)->orWhereNull('c.es_manual'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ventanas as v')->whereColumn('v.cotizacion_id', 'c.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('cotizacion_detalles as d')
                ->whereColumn('d.cotizacion_id', 'c.id')->where('d.tipo_item', 'winperfil'))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('cotizacion_detalles as d')
                ->whereColumn('d.cotizacion_id', 'c.id')->where('d.total', '>', 0))
            ->pluck('c.id');

        foreach ($ids as $id) {
            $suma = (float) DB::table('cotizacion_detalles')->where('cotizacion_id', $id)->sum('total');
            DB::table('cotizaciones')->where('id', $id)->update(['total' => round($suma)]);
        }
    }

    public function down(): void
    {
        // sin reversa: es solo la corrección de un dato
    }
};
