<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recupera los movimientos Chipax que se insertaron sin chipax_id
 * (por el bug de fillable en el primer run).
 *
 * Estrategia:
 *  1. Los registros huérfanos tienen chipax_id=NULL pero raw->chipax_id != NULL
 *  2. Los actualizamos con el ID correcto desde el JSON
 *  3. Eliminamos duplicados que quedaron por el índice único compuesto
 */
class ChipaxFixOrfanos extends Command
{
    protected $signature   = 'chipax:fix-orfanos';
    protected $description = 'Recupera chipax_id desde el campo raw en registros huérfanos';

    public function handle(): int
    {
        // ── 1. Cuántos huérfanos hay ───────────────────────────────────────
        $total = DB::table('movimientos_bancarios')
            ->whereNull('chipax_id')
            ->whereNotNull('raw')
            ->whereRaw("JSON_EXTRACT(raw, '$.chipax_id') IS NOT NULL")
            ->count();

        $this->info("Huérfanos encontrados: $total");
        if (!$total) {
            $this->warn('Nada que reparar.');
            return 0;
        }

        // ── 2a. Eliminar huérfanos que ya tienen un registro correcto ─────
        //  (el segundo run importó bien el mismo chipax_id → el huérfano es basura)
        DB::statement("
            DELETE o FROM movimientos_bancarios o
            WHERE o.chipax_id IS NULL
              AND o.raw IS NOT NULL
              AND JSON_EXTRACT(o.raw, '$.chipax_id') IS NOT NULL
              AND EXISTS (
                  SELECT 1 FROM (SELECT chipax_id FROM movimientos_bancarios) c
                  WHERE c.chipax_id = JSON_EXTRACT(o.raw, '$.chipax_id')
              )
        ");
        $eliminados = $total - DB::table('movimientos_bancarios')
            ->whereNull('chipax_id')
            ->whereNotNull('raw')
            ->whereRaw("JSON_EXTRACT(raw, '$.chipax_id') IS NOT NULL")
            ->count();
        $this->info("Duplicados eliminados: $eliminados");

        // ── 2b. Restaurar chipax_id en los que quedan únicos ─────────────
        DB::statement("
            UPDATE movimientos_bancarios
            SET chipax_id        = JSON_EXTRACT(raw, '$.chipax_id'),
                chipax_cuenta_id = JSON_EXTRACT(raw, '$.idCuentaCorriente')
            WHERE chipax_id IS NULL
              AND raw IS NOT NULL
              AND JSON_EXTRACT(raw, '$.chipax_id') IS NOT NULL
        ");
        $this->info('chipax_id restaurado en huérfanos únicos.');

        // ── 3. Verificar resultado ────────────────────────────────────────
        $conId    = DB::table('movimientos_bancarios')->whereNotNull('chipax_id')->count();
        $sinId    = DB::table('movimientos_bancarios')->whereNull('chipax_id')->count();
        $pending  = DB::table('movimientos_bancarios')->whereNotNull('chipax_id')->where('conciliado', false)->count();
        $done     = DB::table('movimientos_bancarios')->whereNotNull('chipax_id')->where('conciliado', true)->count();

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Con chipax_id',           $conId],
                ['Sin chipax_id (BCH/manual)', $sinId],
                ['Pendientes de conciliar', $pending],
                ['Ya conciliados',          $done],
            ]
        );

        return 0;
    }
}
