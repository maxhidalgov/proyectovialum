<?php

namespace App\Console\Commands;

use App\Models\DocumentoFacturacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Vincula cada Nota de Crédito (tipo Bsale 2) con la factura que anula/rebaja,
 * poblando `documentos_facturacion.nc_referencia_df_id`.
 *
 * Fuente principal: el endpoint `returns.json` de Bsale (lista de devoluciones). Cada
 * devolución trae `credit_note.id` (la NC) y `reference_document.id` (la factura), ambos
 * por ID de documento Bsale — cruce exacto contra `id_documento_bsale`, sin ambigüedad
 * de folio.
 *
 * Respaldo: para NC sin devolución en ese endpoint, se parsea el XML del DTE
 * (`<FolioRef>` = folio de la factura, `<TpoDocRef>` 33/34 = factura) y se cruza contra
 * `numero_documento_bsale`. (La API documents/{id}/references.json viene vacía, por eso
 * no se usa.)
 *
 * Una vez poblado `nc_referencia_df_id`, la query de conciliación
 * (VentaMovimientoController::disponiblesPorMovimiento) y CxC restan el monto de la NC
 * del saldo por cobrar de la factura, por lo que las facturas totalmente anuladas por NC
 * dejan de aparecer en el listado de "Facturas Bsale" a conciliar.
 *
 * Uso (local o Railway Console):
 *   php artisan bsale:vincular-notas-credito           # aplica
 *   php artisan bsale:vincular-notas-credito --dry-run # solo muestra
 *   php artisan bsale:vincular-notas-credito --relink  # reprocesa también las ya vinculadas
 */
class BsaleVincularNotasCredito extends Command
{
    protected $signature = 'bsale:vincular-notas-credito {--dry-run : No guarda, solo muestra lo que haría} {--relink : Reprocesa también las NC que ya tienen referencia}';
    protected $description = 'Vincula Notas de Crédito de Bsale con su factura anulada (parseando el FolioRef del XML del DTE)';

    public function handle(): int
    {
        $base  = rtrim(config('services.bsale.base_url'), '/') . '/';
        $token = config('services.bsale.access_token');
        if (! $token) {
            $this->error('No hay token de Bsale configurado (services.bsale.access_token).');
            return 1;
        }
        $headers = ['access_token' => $token, 'Accept' => 'application/json'];

        $q = DocumentoFacturacion::where('tipo_documento_bsale_id', 2)
            ->whereNotNull('id_documento_bsale');
        if (! $this->option('relink')) {
            $q->whereNull('nc_referencia_df_id');
        }
        $ncs = $q->orderBy('id')->get();

        $this->info("Notas de crédito a procesar: {$ncs->count()}");

        // ── Fuente principal: mapa credit_note.id (Bsale) → reference_document.id (Bsale)
        //    desde el endpoint de devoluciones. ──────────────────────────────────────
        $mapaReturns = $this->cargarMapaDevoluciones($base, $headers);
        $this->line('  Devoluciones Bsale cargadas: ' . count($mapaReturns));

        $vinc = 0; $sinRef = 0; $sinFactura = 0; $errores = 0;

        foreach ($ncs as $nc) {
            $num  = $nc->numero_documento_bsale;
            $facId = null; $via = null;

            // 1) Vía devoluciones: NC (id_documento_bsale) → factura (reference_document.id)
            if (isset($mapaReturns[$nc->id_documento_bsale])) {
                $refDocId = $mapaReturns[$nc->id_documento_bsale];
                $fac = DocumentoFacturacion::where('id_documento_bsale', $refDocId)
                    ->whereNotIn('tipo_documento_bsale_id', [2])
                    ->orderByRaw("FIELD(estado,'emitido') DESC")
                    ->first();
                if ($fac) { $facId = $fac->id; $via = "return→doc $refDocId"; }
            }

            // 2) Respaldo: parsear el XML del DTE (FolioRef → numero_documento_bsale)
            if (! $facId) {
                [$facId, $via, $err] = $this->vincularViaXml($nc, $base, $headers);
                if ($err === 'sin_ref')     { $sinRef++;  $this->line("NC $num: sin referencia (ni return ni FolioRef)"); continue; }
                if ($err === 'sin_factura') { $sinFactura++; $this->warn("NC $num: referencia sin factura local"); continue; }
                if ($err === 'error')       { $errores++; continue; }
            }

            if (! $facId) { $sinFactura++; continue; }

            $fac = DocumentoFacturacion::find($facId);
            $this->line("NC $num (\$" . number_format($nc->monto, 0, ',', '.') . ") → factura folio {$fac->numero_documento_bsale} id={$fac->id} ($via)");

            if (! $this->option('dry-run')) {
                $nc->nc_referencia_df_id = $fac->id;
                $nc->save();
            }
            $vinc++;
        }

        $this->newLine();
        $this->info("Vinculadas: $vinc  |  Sin referencia: $sinRef  |  Sin factura local: $sinFactura  |  Errores: $errores");
        if ($this->option('dry-run')) {
            $this->comment('DRY-RUN: no se guardó nada. Corre sin --dry-run para aplicar.');
        }

        return 0;
    }

    /**
     * Recorre returns.json (paginado) y devuelve el mapa
     * [credit_note.id (Bsale) => reference_document.id (Bsale)].
     */
    private function cargarMapaDevoluciones(string $base, array $headers): array
    {
        $mapa = [];
        $offset = 0; $limit = 50;
        do {
            try {
                $resp = Http::withHeaders($headers)->timeout(25)
                    ->get($base . 'returns.json', ['limit' => $limit, 'offset' => $offset]);
            } catch (\Throwable $e) {
                $this->warn('  No se pudo leer returns.json: ' . $e->getMessage());
                break;
            }
            if (! $resp->successful()) break;
            $json  = $resp->json();
            $items = $json['items'] ?? [];
            foreach ($items as $ret) {
                $ncId  = $ret['credit_note']['id']       ?? null;
                $refId = $ret['reference_document']['id'] ?? null;
                if ($ncId && $refId) $mapa[(int) $ncId] = (int) $refId;
            }
            $total  = (int) ($json['count'] ?? 0);
            $offset += $limit;
        } while ($offset < $total);

        return $mapa;
    }

    /**
     * Respaldo: parsea el XML del DTE de la NC para obtener el FolioRef de la factura.
     * Devuelve [facturaLocalId|null, via|null, error|null] donde error ∈ {sin_ref, sin_factura, error}.
     */
    private function vincularViaXml(DocumentoFacturacion $nc, string $base, array $headers): array
    {
        try {
            $doc = Http::withHeaders($headers)->timeout(20)
                ->get($base . "documents/{$nc->id_documento_bsale}.json")->json();
        } catch (\Throwable $e) {
            return [null, null, 'error'];
        }
        $xmlUrl = $doc['urlXml'] ?? null;
        if (! $xmlUrl) return [null, null, 'error'];

        $xml = @file_get_contents($xmlUrl);
        if (! $xml) return [null, null, 'error'];

        if (! preg_match('/<FolioRef>(.*?)<\/FolioRef>/', $xml, $m)) {
            return [null, null, 'sin_ref'];
        }
        $folioRef = (int) trim($m[1]);

        $fac = DocumentoFacturacion::where('numero_documento_bsale', $folioRef)
            ->whereNotIn('tipo_documento_bsale_id', [1, 2])
            ->orderByRaw("FIELD(estado,'emitido') DESC")
            ->first();

        if (! $fac) return [null, null, 'sin_factura'];

        return [$fac->id, "xml FolioRef $folioRef", null];
    }
}
