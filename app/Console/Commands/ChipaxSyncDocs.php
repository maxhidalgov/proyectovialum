<?php

namespace App\Console\Commands;

use App\Services\ChipaxApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza la información de conciliación desde la API oficial de Chipax
 * (/v2/flujo-caja/cartolas) que incluye documentos vinculados por movimiento.
 *
 * Por cada movimiento con chipax_id, actualiza:
 *  - raw.linked_docs              → resumen de docs vinculados en Chipax
 *  - raw.chipax_monto_conciliado  → monto total conciliado en Chipax
 *  - conciliado                   → true si suma de docs >= monto del movimiento
 *
 * Uso:
 *   php artisan chipax:sync-docs
 *   php artisan chipax:sync-docs --desde=2026-01-01 --hasta=2026-05-31
 *   php artisan chipax:sync-docs --dry-run
 *
 * Requiere en .env:
 *   CHIPAX_APP_ID=<tu_app_id>
 *   CHIPAX_SECRET_KEY=<tu_secret_key>
 */
class ChipaxSyncDocs extends Command
{
    protected $signature = 'chipax:sync-docs
                            {--desde=           : Fecha inicio (Y-m-d), default 01-01 del año actual}
                            {--hasta=           : Fecha fin (Y-m-d), default hoy}
                            {--cuenta=          : ID Chipax de la cuenta corriente (vacío = todas)}
                            {--dry-run          : Solo muestra estadísticas, no actualiza}';

    protected $description = 'Sincroniza docs conciliados desde la API oficial de Chipax';

    public function handle(): int
    {
        $desde  = $this->option('desde') ?: now()->startOfYear()->format('Y-m-d');
        $hasta  = $this->option('hasta') ?: now()->format('Y-m-d');
        $cuenta = $this->option('cuenta') ? (int) $this->option('cuenta') : null;
        $dry    = $this->option('dry-run');
        // Nota: el endpoint ignora perPage y devuelve ~500 items por página

        $this->info("🔗 Chipax API → sincronizar docs conciliados $desde → $hasta");
        if ($dry) $this->warn('   [DRY-RUN: no se modificará la BD]');

        try {
            $api = new ChipaxApiService();

            // Verificar credenciales con primer request
            $this->line('⟳ Autenticando con la API oficial de Chipax...');
            $api->getToken();
            $this->info('   ✅ Token obtenido correctamente');

        } catch (\Throwable $e) {
            $this->error('❌ ' . $e->getMessage());
            $this->line('   Verifica CHIPAX_APP_ID y CHIPAX_SECRET_KEY en .env');
            return 1;
        }

        $page         = 1;
        $totalPages   = 1;
        $updated      = 0;
        $sinDocs      = 0;
        $noEncontrado = 0;
        $bar          = null;

        do {
            try {
                $data = $api->getCartolasConDocs($desde, $hasta, $page, $cuenta);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Error en página $page: " . $e->getMessage());
                break;
            }

            $docs = $data['docs'] ?? [];

            if ($page === 1) {
                $total      = $data['total'] ?? count($docs);
                $totalPages = $data['pages'] ?? 1;
                $this->line("  → $total movimientos en $totalPages páginas");
                $bar = $this->output->createProgressBar($totalPages);
                $bar->start();
            }

            foreach ($docs as $doc) {
                $chipaxId = $doc['id'] ?? null;
                if (!$chipaxId) continue;

                // Extraer resumen de documentos vinculados
                $linkedDocs     = $this->extractLinkedDocs($doc);
                $montoVinculado = collect($linkedDocs)->sum('monto');

                if (empty($linkedDocs)) {
                    $sinDocs++;
                    continue;
                }

                // Buscar en nuestra BD por chipax_id
                $mov = DB::table('movimientos_bancarios')
                    ->where('chipax_id', $chipaxId)
                    ->first();

                if (!$mov) {
                    $noEncontrado++;
                    continue;
                }

                if (!$dry) {
                    // Merge con el raw existente
                    $raw = is_string($mov->raw) ? json_decode($mov->raw, true) : (array) ($mov->raw ?? []);
                    $raw['linked_docs']             = $linkedDocs;
                    $raw['chipax_monto_conciliado'] = $montoVinculado;

                    DB::table('movimientos_bancarios')
                        ->where('id', $mov->id)
                        ->update([
                            'raw'        => json_encode($raw),
                            'conciliado' => $montoVinculado >= (float) $mov->monto,
                        ]);
                }

                $updated++;
            }

            $bar?->advance();
            $page++;

            if ($page % 10 === 0) usleep(500_000); // 0.5s cada 10 páginas

        } while ($page <= $totalPages);

        $bar?->finish();
        $this->newLine(2);

        $action = $dry ? 'habrían sido actualizados' : 'actualizados';

        $this->info('✅ Sincronización completa:');
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ["Con docs vinculados ($action)", $updated],
                ['Sin documentos vinculados en Chipax', $sinDocs],
                ['chipax_id no encontrado en BD local', $noEncontrado],
            ]
        );

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Extrae resumen de todos los documentos vinculados al movimiento.
     * Tipos: Dtes, Compras, Gasto, Honorario, BoletaTercero,
     *        Remuneracion, Previred, Impuesto, Ots
     */
    private function extractLinkedDocs(array $doc): array
    {
        $linked = [];

        // ── DTEs (facturas emitidas / recibidas) ─────────────────────────────
        foreach ($doc['Dtes'] ?? [] as $dte) {
            $monto = (float) ($dte['CartolaDocumento']['monto'] ?? 0);
            if ($monto == 0) continue;
            $linked[] = [
                'tipo'   => 'DTE',
                'modelo' => 'Dtes',
                'id'     => $dte['id'],
                'folio'  => $dte['folio'] ?? null,
                'razon'  => $dte['razon_social'] ?? null,
                'rut'    => $dte['rut'] ?? null,
                'monto'  => $monto,
                'label'  => 'Factura #' . ($dte['folio'] ?? $dte['id'])
                            . ($dte['razon_social'] ? ' · ' . $dte['razon_social'] : ''),
            ];
        }

        // ── Compras ──────────────────────────────────────────────────────────
        foreach ($doc['Compras'] ?? [] as $c) {
            $monto = (float) ($c['CartolaDocumento']['monto'] ?? 0);
            if ($monto == 0) continue;
            $linked[] = [
                'tipo'   => 'Compra',
                'modelo' => 'Compras',
                'id'     => $c['id'],
                'folio'  => $c['folio'] ?? null,
                'razon'  => $c['razon_social'] ?? null,
                'rut'    => $c['rut_emisor'] ?? null,
                'monto'  => $monto,
                'label'  => 'Compra #' . ($c['folio'] ?? $c['id'])
                            . ($c['razon_social'] ? ' · ' . $c['razon_social'] : ''),
            ];
        }

        // ── Gastos ───────────────────────────────────────────────────────────
        foreach ($doc['Gasto'] ?? [] as $g) {
            $monto = (float) ($g['CartolaDocumento']['monto'] ?? 0);
            if ($monto == 0) continue;
            $linked[] = [
                'tipo'   => 'Gasto',
                'modelo' => 'Gasto',
                'id'     => $g['id'],
                'monto'  => $monto,
                'label'  => 'Gasto: ' . ($g['descripcion'] ?? $g['proveedor'] ?? 'sin desc.'),
            ];
        }

        // ── Honorarios ───────────────────────────────────────────────────────
        foreach ($doc['Honorario'] ?? [] as $h) {
            $monto = (float) ($h['CartolaDocumento']['monto'] ?? 0);
            if ($monto == 0) continue;
            $linked[] = [
                'tipo'   => 'Honorario',
                'modelo' => 'Honorario',
                'id'     => $h['id'],
                'monto'  => $monto,
                'label'  => 'Honorario: ' . ($h['nombre_emisor'] ?? $h['rut_emisor'] ?? ''),
            ];
        }

        // ── Boletas de terceros ───────────────────────────────────────────────
        foreach ($doc['BoletaTercero'] ?? [] as $b) {
            $monto = (float) ($b['CartolaDocumento']['monto'] ?? 0);
            if ($monto == 0) continue;
            $linked[] = [
                'tipo'   => 'Boleta',
                'modelo' => 'BoletaTercero',
                'id'     => $b['id'],
                'monto'  => $monto,
                'label'  => 'Boleta #' . ($b['numero_boleta'] ?? $b['id']),
            ];
        }

        // ── Remuneraciones ───────────────────────────────────────────────────
        foreach ($doc['Remuneracion'] ?? [] as $r) {
            $monto = (float) ($r['CartolaDocumento']['monto'] ?? 0);
            if ($monto == 0) continue;
            $emp  = $r['Empleado'] ?? null;
            $nombre = $emp ? trim(($emp['nombre'] ?? '') . ' ' . ($emp['apellido'] ?? '')) : 'empleado';
            $linked[] = [
                'tipo'   => 'Remuneracion',
                'modelo' => 'Remuneracion',
                'id'     => $r['id'],
                'monto'  => $monto,
                'label'  => 'Remuneración: ' . $nombre,
            ];
        }

        // ── Previred ─────────────────────────────────────────────────────────
        foreach ($doc['Previred'] ?? [] as $p) {
            $monto = (float) ($p['CartolaDocumento']['monto'] ?? 0);
            if ($monto == 0) continue;
            $linked[] = [
                'tipo'   => 'Previred',
                'modelo' => 'Previred',
                'id'     => $p['id'],
                'monto'  => $monto,
                'label'  => 'Previred ' . ($p['periodo'] ?? ''),
            ];
        }

        // ── Impuestos ────────────────────────────────────────────────────────
        foreach ($doc['Impuesto'] ?? [] as $imp) {
            $monto = (float) ($imp['CartolaDocumento']['monto'] ?? 0);
            if ($monto == 0) continue;
            $linked[] = [
                'tipo'   => 'Impuesto',
                'modelo' => 'Impuesto',
                'id'     => $imp['id'],
                'monto'  => $monto,
                'label'  => 'Impuesto ' . ($imp['periodo'] ?? ''),
            ];
        }

        // ── OTs ──────────────────────────────────────────────────────────────
        foreach ($doc['Ots'] ?? [] as $ot) {
            $monto = (float) ($ot['CartolaDocumento']['monto'] ?? 0);
            if ($monto == 0) continue;
            $linked[] = [
                'tipo'   => 'OT',
                'modelo' => 'Ots',
                'id'     => $ot['id'],
                'folio'  => $ot['folio'] ?? null,
                'monto'  => $monto,
                'label'  => 'OT #' . ($ot['folio'] ?? $ot['id']),
            ];
        }

        return $linked;
    }
}
