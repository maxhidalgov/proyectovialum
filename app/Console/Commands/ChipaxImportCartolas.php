<?php

namespace App\Console\Commands;

use App\Models\MovimientoBancario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Importa movimientos bancarios desde Chipax a la tabla movimientos_bancarios.
 *
 * Uso:
 *   php artisan chipax:importar
 *   php artisan chipax:importar --desde=2026-01-01 --hasta=2026-05-31
 *   php artisan chipax:importar --cuenta=8401          (solo Banco Chile)
 *
 * Requiere en .env:
 *   CHIPAX_COOKIE=e2e4cb...   (valor de la cookie "Chipax=" del browser)
 *   CHIPAX_BASE_URL=https://manhattan.chipax.com
 */
class ChipaxImportCartolas extends Command
{
    protected $signature = 'chipax:importar
                            {--desde=2026-01-01    : Fecha inicio (Y-m-d)}
                            {--hasta=              : Fecha fin (Y-m-d), default hoy}
                            {--cuenta=             : ID de cuenta Chipax (vacío = todas)}
                            {--dry-run             : Solo muestra cuántos habría, no inserta}';

    protected $description = 'Importa movimientos bancarios desde Chipax';

    private const PER_PAGE = 100;

    public function handle(): int
    {
        $cookie  = config('services.chipax.cookie');
        $baseUrl = config('services.chipax.base_url', 'https://manhattan.chipax.com');

        if (!$cookie) {
            $this->error('❌ Falta CHIPAX_COOKIE en .env');
            $this->line('   Agrega: CHIPAX_COOKIE=<valor de la cookie Chipax del browser>');
            return 1;
        }

        $desde   = $this->option('desde')  ?: '2026-01-01';
        $hasta   = $this->option('hasta')  ?: now()->format('Y-m-d');
        $cuenta  = $this->option('cuenta') ?: null;
        $dryRun  = $this->option('dry-run');

        $this->info("🏦 Chipax → importar cartolas $desde → $hasta" . ($cuenta ? " (cuenta $cuenta)" : " (todas las cuentas)"));
        if ($dryRun) $this->warn('   [DRY-RUN: no se insertará nada]');

        // ── Paso 1: obtener IDs pendientes de conciliación ─────────────────
        $this->line('⟳ Obteniendo movimientos pendientes de conciliación...');
        $pendingIds = $this->fetchAllIds($baseUrl, $cookie, $desde, $hasta, $cuenta, porConciliar: true);
        $this->line('  → ' . count($pendingIds) . ' pendientes');

        // ── Paso 2: importar todos los movimientos ──────────────────────────
        $this->line('⟳ Importando todos los movimientos...');

        $page       = 1;
        $totalPages = 1;
        $insertados = 0;
        $omitidos   = 0;
        $bar        = null;

        do {
            $resp = $this->fetchPage($baseUrl, $cookie, $desde, $hasta, $cuenta, $page, porConciliar: false);
            if ($resp === null) break;

            if ($page === 1) {
                $totalPages = $resp['pagination']['totalPages'] ?? 1;
                $total      = $resp['pagination']['count'] ?? 0;
                $this->line("  → $total movimientos en $totalPages páginas");
                $bar = $this->output->createProgressBar($totalPages);
                $bar->start();
            }

            foreach ($resp['items'] ?? [] as $item) {
                if ($dryRun) { $insertados++; continue; }

                $guardado = $this->importItem($item, $pendingIds);
                $guardado ? $insertados++ : $omitidos++;
            }

            $bar?->advance();
            $page++;

            // Respetar rate-limit de Chipax (60 req/min → esperar 1 seg cada 10 páginas)
            if ($page % 10 === 0) usleep(1_000_000);

        } while ($page <= $totalPages);

        $bar?->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("✅ DRY-RUN: se habrían procesado ~$insertados movimientos.");
        } else {
            $this->info("✅ Importación completa: $insertados insertados/actualizados, $omitidos sin cambios.");
        }

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function fetchPage(
        string $base, string $cookie,
        string $desde, string $hasta,
        ?string $cuenta, int $page,
        bool $porConciliar
    ): ?array {
        $params = [
            'perPage'      => self::PER_PAGE,
            'page'         => $page,
            'porConciliar' => $porConciliar ? 'true' : 'false',
            // Probar distintos nombres que usa Chipax (el que no reconoce lo ignora)
            'fechaDesde'   => $desde,
            'fechaHasta'   => $hasta,
            'desde'        => $desde,
            'hasta'        => $hasta,
            'startDate'    => $desde,
            'endDate'      => $hasta,
        ];
        if ($cuenta) $params['idCuentaCorriente'] = $cuenta;

        $resp = Http::timeout(30)
            ->withHeaders([
                'Cookie'          => 'Chipax=' . $cookie,
                'Accept'          => 'application/json',
                'X-Requested-With'=> 'XMLHttpRequest',
            ])
            ->get("$base/cartolas/legacy", $params);

        if (!$resp->ok()) {
            $this->newLine();
            $this->error("HTTP {$resp->status()} en página $page");
            if ($resp->status() === 401) {
                $this->warn('La cookie Chipax expiró. Ve a app.chipax.com, abre DevTools → Network → copia el nuevo valor de la cookie Chipax y actualiza CHIPAX_COOKIE en .env');
            }
            return null;
        }

        return $resp->json();
    }

    private function fetchAllIds(
        string $base, string $cookie,
        string $desde, string $hasta,
        ?string $cuenta, bool $porConciliar
    ): array {
        $ids = [];
        $page = 1;
        do {
            $data = $this->fetchPage($base, $cookie, $desde, $hasta, $cuenta, $page, $porConciliar);
            if (!$data) break;
            foreach ($data['items'] ?? [] as $item) {
                $ids[$item['id']] = true;
            }
            $page++;
        } while ($page <= ($data['pagination']['totalPages'] ?? 0));
        return $ids;
    }

    private function importItem(array $item, array $pendingIds): bool
    {
        // Detectar si está conciliado en Chipax:
        // 1. No está en la lista de pendientes (porConciliar=true)
        // 2. Tiene documentos asociados (idCartolasDocumentos > 0)
        $conciliadoChipax = !isset($pendingIds[$item['id']])
            || (($item['Saldo']['idCartolasDocumentos'] ?? 0) > 0);

        $monto  = $item['abono'] > 0 ? $item['abono'] : -abs($item['cargo']);
        $tipo   = $item['abono'] > 0 ? 'C' : 'D';
        $cuenta = $item['CuentaCorriente']['numeroCuenta'] ?? 'CHIPAX';
        $banco  = $item['CuentaCorriente']['banco'] ?? '';

        // Descripción enriquecida con datos de transferencia si existen
        $desc = $item['descripcion'] ?? '';
        if (!empty($item['CartolaTransferencia']['nombreOrigen'])) {
            $origen = trim($item['CartolaTransferencia']['nombreOrigen']);
            if ($origen && !str_contains($desc, $origen)) {
                $desc = "$origen · $desc";
            }
        }
        if (!empty($item['CartolaTransferencia']['nombreDestino'])) {
            $destino = trim($item['CartolaTransferencia']['nombreDestino']);
            if ($destino && !str_contains($desc, $destino)) {
                $desc = "$destino · $desc";
            }
        }

        try {
            MovimientoBancario::updateOrCreate(
                ['chipax_id' => $item['id']],
                [
                    'chipax_cuenta_id'   => $item['idCuentaCorriente'],
                    'cuenta'             => substr($cuenta, 0, 30),
                    'fecha_contable'     => $item['fecha'],
                    'fecha_valor'        => $item['fecha'],
                    'descripcion'        => substr($desc, 0, 255),
                    'glosa'              => substr($item['descripcion'] ?? '', 0, 255),
                    'monto'              => $monto,
                    'tipo'               => $tipo,
                    'numero_documento'   => ($item['numeroDocumento'] ?? 0) > 0
                                            ? (string) $item['numeroDocumento']
                                            : null,
                    'saldo_disponible'   => $item['saldo'] ?? null,
                    // Solo marcamos conciliado si ya estaba así en Chipax
                    // No sobreescribimos si el usuario ya concilió en nuestro sistema
                    'conciliado'         => $conciliadoChipax,
                    // Guardamos solo campos clave, no el objeto completo (ahorra ~70MB)
                    'raw'                => [
                        'chipax_id'           => $item['id'],
                        'idCuentaCorriente'   => $item['idCuentaCorriente'],
                        'banco'               => $item['CuentaCorriente']['banco'] ?? null,
                        'numeroDocumento'     => $item['numeroDocumento'] ?? null,
                        'idCargasCartolas'    => $item['idCargasCartolas'] ?? null,
                        'idCartolasDocumentos'=> $item['Saldo']['idCartolasDocumentos'] ?? null,
                    ],
                ]
            );
            return true;
        } catch (\Exception $e) {
            $this->newLine();
            $this->warn("⚠ Error en item {$item['id']}: " . $e->getMessage());
            return false;
        }
    }
}
