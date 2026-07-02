<?php

namespace App\Console\Commands;

use App\Services\WorkeraService;
use Illuminate\Console\Command;

/**
 * Sincroniza los empleados de Workera con la tabla local, cruzando por RUT.
 * Vincula el `workera_code` a los empleados existentes.
 *
 * Uso (local o Railway Console):
 *   php artisan workera:sync
 */
class WorkeraSync extends Command
{
    protected $signature   = 'workera:sync';
    protected $description = 'Vincula empleados locales con Workera (cruce por RUT)';

    public function handle(WorkeraService $workera): int
    {
        $this->info('Sincronizando empleados con Workera...');

        $r = $workera->syncEmpleados();

        $this->info("Vinculados: {$r['synced']}  |  Sin match local: {$r['skipped']}");
        $this->line('Los empleados sin match no existen en la tabla local — créalos y vuelve a sincronizar.');

        return 0;
    }
}
