<?php

namespace App\Console\Commands;

use App\Models\Cotizacion;
use App\Models\CotizacionEstadoHistorial;
use Illuminate\Console\Command;

class HistorialBackfill extends Command
{
    protected $signature = 'historial:backfill {--dry-run}';
    protected $description = 'Siembra un punto inicial en el historial de estados para cotizaciones existentes (fecha aproximada = updated_at)';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $creados = 0;

        Cotizacion::whereNotNull('estado_produccion')
            ->whereDoesntHave('historialEstados', fn ($q) => $q->where('tipo', 'produccion'))
            ->chunkById(200, function ($cots) use (&$creados, $dry) {
                foreach ($cots as $c) {
                    $this->line("  #{$c->id} · {$c->estado_produccion} · " . ($c->updated_at?->toDateString() ?? '—'));
                    if (!$dry) {
                        CotizacionEstadoHistorial::create([
                            'cotizacion_id' => $c->id,
                            'tipo'          => 'produccion',
                            'estado'        => $c->estado_produccion,
                            'fecha'         => $c->updated_at ?? now(),
                        ]);
                    }
                    $creados++;
                }
            });

        $this->info(($dry ? '[dry-run] ' : '') . "Puntos de historial a crear: {$creados}");
        $this->comment('Nota: es una fecha aproximada (updated_at). El seguimiento exacto empieza desde ahora.');

        return self::SUCCESS;
    }
}
