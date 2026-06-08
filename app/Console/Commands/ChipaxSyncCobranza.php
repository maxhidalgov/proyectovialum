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
        $bar          = null;

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
                $folio       = (string) ($dte['folio'] ?? '');
                $saldoDeudor = isset($dte['Saldo']['saldoDeudor']) ? (float) $dte['Saldo']['saldoDeudor'] : null;

                if (!$folio || $saldoDeudor === null) continue;

                $affected = DB::table('documentos_facturacion')
                    ->where('numero_documento_bsale', $folio)
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
        $this->info("Completado: {$processed} DTEs procesados | {$actualizados} actualizados | {$noMatch} sin match local.");

        return 0;
    }
}
