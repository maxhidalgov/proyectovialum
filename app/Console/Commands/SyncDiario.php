<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Actualización diaria de la base: agrupa las sincronizaciones que antes
 * se disparaban a mano con botones.
 *
 *   php artisan sync:diario
 *
 * Pensado para correr una vez al día a una hora fija (Railway Cron o worker).
 */
class SyncDiario extends Command
{
    protected $signature = 'sync:diario
                            {--solo-ventas  : Solo ventas de Bsale}
                            {--solo-compras : Solo compras de Bsale}';

    protected $description = 'Actualización diaria: sincroniza ventas y compras (Bsale) + estado de cobranza (Chipax)';

    public function handle(): int
    {
        $inicio = now();
        $this->info("== sync:diario iniciado {$inicio->toDateTimeString()} ==");
        Log::info('sync:diario iniciado');

        // 1) Ventas + compras desde Bsale (incremental)
        $bsaleOpts = [];
        if ($this->option('solo-ventas'))  $bsaleOpts['--solo-ventas']  = true;
        if ($this->option('solo-compras')) $bsaleOpts['--solo-compras'] = true;

        try {
            $this->info('→ bsale:sync');
            $this->call('bsale:sync', $bsaleOpts);
        } catch (\Throwable $e) {
            $this->error('bsale:sync falló: ' . $e->getMessage());
            Log::error('sync:diario bsale:sync', ['error' => $e->getMessage()]);
        }

        // 2) Estado de cobranza (qué facturas están pagadas) desde Chipax
        if (!$this->option('solo-ventas') && !$this->option('solo-compras')) {
            try {
                $this->info('→ chipax:sync-cobranza');
                $this->call('chipax:sync-cobranza');
            } catch (\Throwable $e) {
                $this->error('chipax:sync-cobranza falló: ' . $e->getMessage());
                Log::error('sync:diario chipax:sync-cobranza', ['error' => $e->getMessage()]);
            }
        }

        $seg = $inicio->diffInSeconds(now());
        $this->info("== sync:diario terminado ({$seg}s) ==");
        Log::info('sync:diario terminado', ['segundos' => $seg]);

        return self::SUCCESS;
    }
}
