<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BsaleVentaSyncController;
use App\Http\Controllers\CompraController;

/**
 * Sincroniza ventas y compras desde Bsale API.
 *
 * Uso manual:
 *   php artisan bsale:sync                   # ventas año actual + compras smart
 *   php artisan bsale:sync --solo-ventas
 *   php artisan bsale:sync --solo-compras
 *   php artisan bsale:sync --años=2024,2025  # sync histórico ventas
 *   php artisan bsale:sync --full            # ventas todos los años desde 2024
 *
 * Ejecución automática: cada 4 horas vía scheduler (ver routes/console.php).
 */
class BsaleSyncAll extends Command
{
    protected $signature = 'bsale:sync
                            {--solo-ventas  : Solo sincronizar facturas de venta}
                            {--solo-compras : Solo sincronizar facturas de compra}
                            {--full         : Ventas: recorrer todos los años desde 2024}
                            {--años=        : Ventas: lista de años separados por coma (ej: 2024,2025)}';

    protected $description = 'Sincroniza ventas y compras desde Bsale API (incremental por defecto)';

    public function handle(): int
    {
        $token = config('services.bsale.access_token');

        if (!$token) {
            $this->error('❌ Falta BSALE_ACCESS_TOKEN en las variables de entorno');
            return 1;
        }

        $soloVentas  = $this->option('solo-ventas');
        $soloCompras = $this->option('solo-compras');
        $ambos       = !$soloVentas && !$soloCompras;

        $this->info('🔄 Bsale Sync — ' . now()->format('Y-m-d H:i'));

        // ── Ventas ────────────────────────────────────────────────────────────
        if ($ambos || $soloVentas) {
            $this->syncVentas();
        }

        // ── Compras ───────────────────────────────────────────────────────────
        if ($ambos || $soloCompras) {
            $this->syncCompras();
        }

        $this->info('✅ Bsale Sync completado — ' . now()->format('H:i:s'));
        return 0;
    }

    // ── Ventas ────────────────────────────────────────────────────────────────

    private function syncVentas(): void
    {
        $this->line('  📄 Sincronizando facturas de venta...');

        try {
            $años = $this->resolverAñosVentas();
            $this->line('     Años: ' . implode(', ', $años));

            // Instancia el controller (misma lógica que el botón manual del frontend)
            $ctrl    = app(BsaleVentaSyncController::class);
            $request = new Request(['años' => $años]);

            ob_start();
            $response = $ctrl->sincronizar($request);
            ob_end_clean();

            $data = json_decode($response->getContent(), true);

            $nuevos   = $data['nuevos']   ?? '?';
            $omitidos = $data['omitidos'] ?? '?';
            $errores  = $data['errores']  ?? '?';

            $this->info("     ✓ Ventas: {$nuevos} nuevas, {$omitidos} ya existían, {$errores} errores");

            Log::info('BsaleSyncAll::ventas', compact('nuevos', 'omitidos', 'errores'));

        } catch (\Throwable $e) {
            $this->error('     ✗ Error sincronizando ventas: ' . $e->getMessage());
            Log::error('BsaleSyncAll::ventas', ['error' => $e->getMessage()]);
        }
    }

    private function resolverAñosVentas(): array
    {
        // --años=2024,2025
        if ($raw = $this->option('años')) {
            return array_map('intval', explode(',', $raw));
        }

        // --full → desde 2024 hasta hoy
        if ($this->option('full')) {
            return range(now()->year, 2024);
        }

        // Por defecto: solo año actual (incremental)
        return [now()->year];
    }

    // ── Compras ───────────────────────────────────────────────────────────────

    private function syncCompras(): void
    {
        $this->line('  🧾 Sincronizando facturas de compra...');

        try {
            // smart=true → empieza por los más recientes, para al primer existente
            $ctrl    = app(CompraController::class);
            $request = new Request(['smart' => true]);

            ob_start();
            $response = $ctrl->sincronizar($request);
            ob_end_clean();

            $data = json_decode($response->getContent(), true);

            $nuevas  = $data['nuevas']       ?? $data['nuevos'] ?? '?';
            $errores = $data['errores']       ?? '?';
            $hasMas  = $data['has_more']      ?? false;

            $msg = "     ✓ Compras: {$nuevas} nuevas, {$errores} errores";
            if ($hasMas) $msg .= ' (hay más — considera correr bsale:sync --solo-compras de nuevo)';
            $this->info($msg);

            Log::info('BsaleSyncAll::compras', compact('nuevas', 'errores'));

        } catch (\Throwable $e) {
            $this->error('     ✗ Error sincronizando compras: ' . $e->getMessage());
            Log::error('BsaleSyncAll::compras', ['error' => $e->getMessage()]);
        }
    }
}
