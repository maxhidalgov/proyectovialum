<?php

namespace App\Console\Commands;

use App\Models\DocumentoFacturacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Vincula cada Nota de Crédito (tipo Bsale 2) con la factura que anula/rebaja,
 * poblando `documentos_facturacion.nc_referencia_df_id`.
 *
 * ¿Por qué se necesita? La API de Bsale (documents/{id}/references.json) devuelve
 * vacío para estas NC, así que la referencia NO está disponible por ahí. Sin embargo,
 * el XML del DTE sí trae la referencia SII: <FolioRef> (folio de la factura) y
 * <TpoDocRef> (33/34 = factura afecta/exenta). Con el FolioRef cruzamos contra
 * `numero_documento_bsale` para encontrar la factura local.
 *
 * Una vez poblado `nc_referencia_df_id`, la query de conciliación
 * (VentaMovimientoController::disponiblesPorMovimiento) resta el monto de la NC del
 * saldo por cobrar de la factura, por lo que las facturas totalmente anuladas por NC
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

        $vinc = 0; $sinRef = 0; $sinFactura = 0; $errores = 0;

        foreach ($ncs as $nc) {
            $num = $nc->numero_documento_bsale;

            // 1) Documento JSON → urlXml
            try {
                $doc = Http::withHeaders($headers)->timeout(20)
                    ->get($base . "documents/{$nc->id_documento_bsale}.json")->json();
            } catch (\Throwable $e) {
                $this->warn("NC $num: error al pedir documento Bsale ({$e->getMessage()})");
                $errores++;
                continue;
            }
            $xmlUrl = $doc['urlXml'] ?? null;
            if (! $xmlUrl) {
                $this->warn("NC $num: sin urlXml");
                $errores++;
                continue;
            }

            // 2) Descargar XML y extraer FolioRef / TpoDocRef
            $xml = @file_get_contents($xmlUrl);
            if (! $xml) {
                $this->warn("NC $num: no se pudo descargar el XML");
                $errores++;
                continue;
            }
            if (! preg_match('/<FolioRef>(.*?)<\/FolioRef>/', $xml, $mFolio)) {
                $this->line("NC $num: XML sin FolioRef (no referencia ninguna factura)");
                $sinRef++;
                continue;
            }
            $folioRef = (int) trim($mFolio[1]);
            $tpoRef   = preg_match('/<TpoDocRef>(.*?)<\/TpoDocRef>/', $xml, $mT) ? (int) trim($mT[1]) : null;

            // 3) Buscar la factura local por su folio SII. Excluimos NC (tipo 2) y
            //    boletas (tipo 1) del destino; nos interesan facturas.
            $fac = DocumentoFacturacion::where('numero_documento_bsale', $folioRef)
                ->whereNotIn('tipo_documento_bsale_id', [1, 2])
                ->orderByRaw("FIELD(estado,'emitido') DESC")
                ->first();

            if (! $fac) {
                $this->warn("NC $num → FolioRef $folioRef (TpoDocRef $tpoRef): no hay factura local con ese folio");
                $sinFactura++;
                continue;
            }

            $this->line("NC $num (\$" . number_format($nc->monto, 0, ',', '.') . ") → factura folio $folioRef id={$fac->id} (\$" . number_format($fac->monto, 0, ',', '.') . ")");

            if (! $this->option('dry-run')) {
                $nc->nc_referencia_df_id = $fac->id;
                $nc->save();
            }
            $vinc++;
        }

        $this->newLine();
        $this->info("Vinculadas: $vinc  |  Sin FolioRef: $sinRef  |  Sin factura local: $sinFactura  |  Errores: $errores");
        if ($this->option('dry-run')) {
            $this->comment('DRY-RUN: no se guardó nada. Corre sin --dry-run para aplicar.');
        }

        return 0;
    }
}
