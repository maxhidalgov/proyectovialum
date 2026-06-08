<?php

namespace App\Console\Commands;

use App\Services\ChipaxApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ChipaxSyncCobranza extends Command
{
    protected $signature   = 'chipax:sync-cobranza {--limit=0 : Límite de DTEs a procesar (0 = todos)}';
    protected $description = 'Sincroniza monto_por_cobrar (saldoDeudor) desde Chipax /dtes para CxC';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $this->info('Sincronizando saldoDeudor desde Chipax API oficial /dtes...');

        try {
            $api   = new ChipaxApiService();
            $token = $api->getToken();
        } catch (\Throwable $e) {
            $this->error('Error autenticando con Chipax: ' . $e->getMessage());
            return 1;
        }

        $page         = 1;
        $totalPages   = 1;
        $processed    = 0;
        $actualizados = 0;
        $noMatch      = 0;
        // Buffer de resúmenes mensuales de boletas (tipo 39, folio -YYYYMM)
        $boletasMensuales = [];
        $bar              = null;

        do {
            $resp = Http::timeout(30)
                ->withHeaders(['Authorization' => 'JWT ' . $token])
                ->get('https://api.chipax.com/v2/dtes', ['page' => $page, 'perPage' => 50]);

            if ($resp->failed()) {
                $this->error("Error API Chipax: HTTP {$resp->status()} en página {$page}");
                return 1;
            }

            $data  = $resp->json();
            $items = $data['items'] ?? [];

            if ($page === 1) {
                $totalPages = $data['paginationAttributes']['totalPages'] ?? 1;
                $total      = $data['paginationAttributes']['count'] ?? count($items);
                $this->line("  → {$total} DTEs en {$totalPages} páginas");
                $bar = $this->output->createProgressBar($totalPages);
                $bar->start();
            }

            if (empty($items)) break;

            foreach ($items as $dte) {
                $folio       = $dte['folio'] ?? null;
                $tipo        = (int) ($dte['tipo'] ?? 0);
                $saldoDeudor = isset($dte['Saldo']['saldoDeudor']) ? (float) $dte['Saldo']['saldoDeudor'] : null;
                $montoTotal  = (float) ($dte['montoTotal'] ?? 0);

                if ($folio === null || $saldoDeudor === null) continue;

                // Resumen mensual de boletas: tipo 39, folio negativo (-YYYYMM)
                if ($tipo === 39 && (int) $folio < 0) {
                    // folio = -202509 → yearMonth = '2025-09'
                    $ym = abs((int) $folio);
                    $yearMonth = substr((string) $ym, 0, 4) . '-' . substr((string) $ym, 4, 2);
                    // Guardar saldo y monto total del mes (puede haber dos entradas por mes, sumar)
                    if (!isset($boletasMensuales[$yearMonth])) {
                        $boletasMensuales[$yearMonth] = ['saldo' => 0.0, 'monto' => 0.0];
                    }
                    $boletasMensuales[$yearMonth]['saldo'] += $saldoDeudor;
                    $boletasMensuales[$yearMonth]['monto'] += $montoTotal;
                    $processed++;
                    continue;
                }

                // Documento individual (factura, NC, etc.): match por folio
                $folioStr = (string) $folio;
                $affected = DB::table('documentos_facturacion')
                    ->where('numero_documento_bsale', $folioStr)
                    ->update([
                        'chipax_monto_por_cobrar'   => $saldoDeudor,
                        'chipax_cobranza_synced_at' => now(),
                        'updated_at'                => now(),
                    ]);

                $affected ? $actualizados++ : $noMatch++;
                $processed++;

                if ($limit > 0 && $processed >= $limit) break 2;
            }

            $bar?->advance();
            $page++;

            if ($page % 10 === 0) usleep(300_000);

        } while ($page <= $totalPages);

        $bar?->finish();
        $this->newLine(2);

        // Aplicar resúmenes mensuales de boletas a las boletas individuales
        if (!empty($boletasMensuales)) {
            $this->info('Aplicando saldos mensuales de boletas (' . count($boletasMensuales) . ' meses)...');
            $boletasActualizadas = 0;

            foreach ($boletasMensuales as $yearMonth => $datosMes) {
                $saldoMes = $datosMes['saldo'];
                $montoMes = $datosMes['monto'];
                [$y, $m] = explode('-', $yearMonth);
                $desde = "{$y}-{$m}-01";
                $hasta = date('Y-m-t', strtotime($desde));

                // Boletas del mes en nuestra BD
                $boletas = DB::table('documentos_facturacion')
                    ->where('tipo_documento_bsale_id', 1)
                    ->whereBetween('fecha_emision', [$desde, $hasta])
                    ->where('estado', 'emitido')
                    ->get(['id', 'monto']);

                if ($boletas->isEmpty()) continue;

                $totalMontoLocal = $boletas->sum('monto');

                foreach ($boletas as $b) {
                    // Distribuir saldo proporcionalmente según el peso de cada boleta en el total del mes
                    $proporcion         = $totalMontoLocal > 0 ? ($b->monto / $totalMontoLocal) : 0;
                    $saldoProporcional  = round($saldoMes * $proporcion, 2);

                    DB::table('documentos_facturacion')->where('id', $b->id)->update([
                        'chipax_monto_por_cobrar'   => $saldoProporcional,
                        'chipax_cobranza_synced_at' => now(),
                        'updated_at'                => now(),
                    ]);
                    $boletasActualizadas++;
                }
            }

            $this->info("  → {$boletasActualizadas} boletas individuales actualizadas con saldo mensual proporcional.");
        }

        $this->info("Completado: {$processed} DTEs procesados | {$actualizados} facturas actualizadas | {$noMatch} sin match local.");

        return 0;
    }
}
