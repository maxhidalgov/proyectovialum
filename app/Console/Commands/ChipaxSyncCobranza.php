<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ChipaxSyncCobranza extends Command
{
    protected $signature   = 'chipax:sync-cobranza {--limit=0 : Límite de DTEs a procesar (0 = todos)}';
    protected $description = 'Sincroniza monto_por_cobrar desde Chipax /dtes para CxC';

    private string $baseUrl = 'https://api.chipax.com/v2/';

    public function handle(): int
    {
        $cookie = env('CHIPAX_COOKIE', '');
        if (!$cookie) {
            $this->error('CHIPAX_COOKIE no configurada');
            return 1;
        }

        $limit        = (int) $this->option('limit');
        $pageSize     = 100;
        $offset       = 0;
        $total        = 0;
        $actualizados = 0;
        $noMatch      = 0;

        $this->info('Sincronizando monto_por_cobrar desde Chipax /dtes...');

        do {
            $res = Http::timeout(30)
                ->withHeaders(['Cookie' => $cookie])
                ->get($this->baseUrl . 'dtes', [
                    'limit'  => $pageSize,
                    'offset' => $offset,
                ]);

            if ($res->failed()) {
                $this->error("Error API Chipax: HTTP {$res->status()}");
                return 1;
            }

            $dtes = $res->json();
            if (empty($dtes) || !is_array($dtes)) break;

            foreach ($dtes as $dte) {
                $folio          = (string) ($dte['folio'] ?? '');
                $montoPorCobrar = isset($dte['monto_por_cobrar']) ? (float) $dte['monto_por_cobrar'] : null;

                if (!$folio || $montoPorCobrar === null) continue;

                $affected = DB::table('documentos_facturacion')
                    ->where('numero_documento_bsale', $folio)
                    ->whereIn('tipo_documento_bsale_id', [1, 3, 4, 5, 6])
                    ->update([
                        'chipax_monto_por_cobrar'   => $montoPorCobrar,
                        'chipax_cobranza_synced_at' => now(),
                        'updated_at'                => now(),
                    ]);

                $affected ? $actualizados++ : $noMatch++;
                $total++;

                if ($limit > 0 && $total >= $limit) break 2;
            }

            $offset += $pageSize;
            $this->line("  Procesados: {$total} (actualizados: {$actualizados})");

        } while (count($dtes) === $pageSize);

        $this->info("Completado: {$total} DTEs | {$actualizados} actualizados | {$noMatch} sin match local.");
        return 0;
    }
}
