<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: las cotizaciones marcadas como "Enviada" antes de que enviado_at
 * fuera fillable quedaron con enviado_at = NULL y no aparecían en Seguimiento.
 * Rellenamos con updated_at (aprox. la fecha de envío) y asumimos WhatsApp.
 */
return new class extends Migration
{
    public function up(): void
    {
        $enviadaId = DB::table('estados_cotizacion')->where('nombre', 'Enviada')->value('id');
        if (!$enviadaId) return;

        DB::table('cotizaciones')
            ->where('estado_cotizacion_id', $enviadaId)
            ->whereNull('enviado_at')
            ->update([
                'enviado_at'  => DB::raw('updated_at'),
                'enviado_via' => DB::raw("COALESCE(enviado_via, 'whatsapp')"),
            ]);
    }

    public function down(): void
    {
        // No revertible con seguridad (perderíamos la fecha real).
    }
};
