<?php

namespace App\Console\Commands;

use App\Http\Controllers\WinperfilController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza presupuestos y pedidos desde Winperfil hacia la BD local.
 * Pensado para el scheduler (detecta cotizaciones aceptadas y las mete al
 * pipeline de producción automáticamente).
 *
 * Requiere que WINPERFIL_URL sea alcanzable (túnel activo en producción).
 *
 * Uso:
 *   php artisan winperfil:sync
 *   php artisan winperfil:sync --serie=A --dias=60
 */
class WinperfilSync extends Command
{
    protected $signature = 'winperfil:sync {--serie=A} {--dias=60}';
    protected $description = 'Sincroniza presupuestos y pedidos desde Winperfil';

    public function handle(): int
    {
        $serie = $this->option('serie');
        $desde = now()->subDays((int) $this->option('dias'))->toDateString();
        $hasta = now()->toDateString();

        $this->info("Winperfil sync — serie {$serie}, desde {$desde} hasta {$hasta}");

        $ctrl = app(WinperfilController::class);
        $req  = new Request(['serie' => $serie, 'desde' => $desde, 'hasta' => $hasta]);

        try {
            $pres = $ctrl->syncPresupuestos($req)->getData(true);
            if (isset($pres['error'])) {
                throw new \RuntimeException($pres['error']);
            }
            $this->info("Presupuestos: total {$pres['total']}, creados {$pres['creados']}, actualizados {$pres['actualizados']}");
            if (!empty($pres['errores'])) {
                $this->warn(count($pres['errores']) . ' presupuestos con error');
            }

            $ped = $ctrl->syncPedidos($req)->getData(true);
            $this->info("Pedidos: total {$ped['total']}, creados {$ped['creados']}, actualizados {$ped['actualizados']}");
        } catch (\Throwable $e) {
            Log::error('winperfil:sync falló', ['error' => $e->getMessage()]);
            $this->error('Error al sincronizar con Winperfil: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
