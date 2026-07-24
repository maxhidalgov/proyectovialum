<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Actualización diaria de la base: agrupa las sincronizaciones que antes
 * se disparaban a mano con botones.
 *
 *   php artisan sync:diario
 *
 * Pensado para correr una vez al día a una hora fija (Railway Cron o worker).
 * Registra cada corrida en la tabla sync_runs (para mostrar el estado en el Home).
 */
class SyncDiario extends Command
{
    protected $signature = 'sync:diario
                            {--solo-ventas  : Solo ventas de Bsale}
                            {--solo-compras : Solo compras de Bsale}';

    protected $description = 'Actualización diaria: sincroniza ventas y compras (Bsale) + clientes';

    public function handle(): int
    {
        $inicio = now();
        $this->info("== sync:diario iniciado {$inicio->toDateTimeString()} ==");
        Log::info('sync:diario iniciado');

        // Conteos antes, para calcular cuántas cosas nuevas trajo el sync
        $antes = $this->conteos();
        $ok = true;

        // 1) Ventas + compras desde Bsale (incremental)
        $bsaleOpts = [];
        if ($this->option('solo-ventas'))  $bsaleOpts['--solo-ventas']  = true;
        if ($this->option('solo-compras')) $bsaleOpts['--solo-compras'] = true;

        try {
            $this->info('→ bsale:sync');
            $this->call('bsale:sync', $bsaleOpts);
        } catch (\Throwable $e) {
            $ok = false;
            $this->error('bsale:sync falló: ' . $e->getMessage());
            Log::error('sync:diario bsale:sync', ['error' => $e->getMessage()]);
        }

        if (!$this->option('solo-ventas') && !$this->option('solo-compras')) {
            // 2) Clientes de Bsale → base local (clientes nuevos / datos actualizados)
            try {
                $this->info('→ bsale:sincronizar-clientes');
                $this->call('bsale:sincronizar-clientes');
            } catch (\Throwable $e) {
                $ok = false;
                $this->error('bsale:sincronizar-clientes falló: ' . $e->getMessage());
                Log::error('sync:diario bsale:sincronizar-clientes', ['error' => $e->getMessage()]);
            }
            // NOTA: chipax:sync-cobranza se sacó del cron (2026-07-24). La cobranza/CxC
            // se maneja con conciliación manual (venta_movimiento), no depende de Chipax.
            // El comando sigue existiendo por si se necesita correr a mano.
        }

        $despues = $this->conteos();
        $seg = $inicio->diffInSeconds(now());

        // Registrar la corrida (para el indicador del Home)
        try {
            DB::table('sync_runs')->insert([
                'comando'         => 'sync:diario',
                'started_at'      => $inicio,
                'finished_at'     => now(),
                'ok'              => $ok,
                'ventas_nuevas'   => max(0, $despues['ventas'] - $antes['ventas']),
                'compras_nuevas'  => max(0, $despues['compras'] - $antes['compras']),
                'clientes_nuevos' => max(0, $despues['clientes'] - $antes['clientes']),
                'detalle'         => "Duración {$seg}s",
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('sync:diario no pudo registrar sync_runs', ['error' => $e->getMessage()]);
        }

        $this->info("== sync:diario terminado ({$seg}s) ==");
        Log::info('sync:diario terminado', ['segundos' => $seg, 'ok' => $ok]);

        return self::SUCCESS;
    }

    /** Conteos actuales para medir el delta del sync. */
    private function conteos(): array
    {
        return [
            'ventas'   => (int) DB::table('documentos_facturacion')->count(),
            'compras'  => (int) DB::table('compras')->count(),
            'clientes' => (int) DB::table('clientes')->count(),
        ];
    }
}
