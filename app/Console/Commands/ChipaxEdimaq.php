<?php

namespace App\Console\Commands;

use App\Services\ChipaxApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Extrae de Chipax las facturas de venta y el saldo de un cliente (por defecto EDIMAQ)
 * antes de dejar de usar Chipax. Uso:
 *   php artisan chipax:edimaq                # busca "EDIMAQ", genera CSV
 *   php artisan chipax:edimaq --q=EDIMAQ     # texto a buscar en razon social/rut
 *   php artisan chipax:edimaq --probe        # solo muestra la forma de un DTE
 */
class ChipaxEdimaq extends Command
{
    protected $signature   = 'chipax:edimaq {--q=EDIMAQ} {--probe}';
    protected $description = 'Extrae de Chipax las ventas y saldo de un cliente (EDIMAQ)';

    public function handle(): int
    {
        $api = new ChipaxApiService();
        try {
            $token = $api->getToken();
        } catch (\Throwable $e) {
            $this->error('Login Chipax falló: ' . $e->getMessage());
            return 1;
        }
        $this->info('Token Chipax OK');

        $headers = ['Authorization' => 'JWT ' . $token];

        if ($this->option('probe')) {
            $resp = Http::withHeaders($headers)->timeout(30)
                ->get('https://api.chipax.com/v2/dtes', ['page' => 1, 'perPage' => 3]);
            $data  = $resp->json();
            $items = $data['items'] ?? [];
            $this->line('pagination: ' . json_encode($data['paginationAttributes'] ?? []));
            if ($items) {
                $this->line('KEYS: ' . implode(', ', array_keys($items[0])));
                $this->line(json_encode($items[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            return 0;
        }

        $q = mb_strtoupper(trim((string) $this->option('q')));
        $this->info("Buscando DTEs de: $q");

        $page = 1; $totalPages = 1; $encontrados = [];
        do {
            $resp = Http::withHeaders($headers)->timeout(30)
                ->get('https://api.chipax.com/v2/dtes', ['page' => $page, 'perPage' => 100]);
            if ($resp->failed()) { $this->error("HTTP {$resp->status()} en pág $page"); break; }
            $data  = $resp->json();
            $items = $data['items'] ?? [];
            if ($page === 1) {
                $totalPages = $data['paginationAttributes']['totalPages'] ?? 1;
                $this->line("Total DTEs: " . ($data['paginationAttributes']['count'] ?? '?') . " en $totalPages págs");
            }
            foreach ($items as $it) {
                $razon = mb_strtoupper((string) ($it['razonSocial'] ?? $it['razon_social'] ?? ''));
                $rut   = (string) ($it['rutReceptor'] ?? $it['rut'] ?? '');
                if ($q !== '' && (str_contains($razon, $q) || str_contains(mb_strtoupper($rut), $q))) {
                    $encontrados[] = $it;
                }
            }
            $page++;
            if ($page % 10 === 0) usleep(200_000);
        } while ($page <= $totalPages);

        $this->info('DTEs encontrados de ' . $q . ': ' . count($encontrados));
        if (empty($encontrados)) return 0;

        // Ordenar por fecha
        usort($encontrados, fn($a, $b) => strcmp($a['fechaEmision'] ?? '', $b['fechaEmision'] ?? ''));

        $tipos = [33 => 'Factura afecta', 34 => 'Factura exenta', 39 => 'Boleta', 41 => 'Boleta exenta',
                  56 => 'Nota de débito', 61 => 'Nota de crédito', 46 => 'Factura de compra', 43 => 'Liquidación factura'];

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $bold = ['font' => ['bold' => true]];
        $hdrStyle = ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1B3A6B']]];

        // ── Hoja 1: Facturas de venta ──────────────────────────────────────────
        $s1 = $ss->getActiveSheet();
        $s1->setTitle('Facturas de venta');
        $cols = ['Folio', 'Tipo', 'Fecha emisión', 'Fecha vencimiento', 'RUT', 'Razón social', 'Neto', 'Exento', 'IVA', 'Total', 'Saldo pendiente', 'Anulado', 'N° pagos', 'Total pagado', 'PDF'];
        $s1->fromArray($cols, null, 'A1');
        $s1->getStyle('A1:O1')->applyFromArray($hdrStyle);
        $row = 2;
        $totFact = 0; $totSaldo = 0; $totPagado = 0;
        foreach ($encontrados as $d) {
            $cartolas = $d['Cartolas'] ?? [];
            // Monto APLICADO a esta factura (no el total de la transferencia, que puede pagar varias)
            $pagado = array_sum(array_map(fn($c) => (float) ($c['CartolaDocumento']['monto'] ?? $c['abono'] ?? 0), $cartolas));
            $saldo  = (float) ($d['Saldo']['saldoDeudor'] ?? 0);
            $total  = (float) ($d['montoTotal'] ?? 0);
            $totFact += $total; $totSaldo += $saldo; $totPagado += $pagado;
            $s1->fromArray([
                $d['folio'] ?? '',
                $tipos[$d['tipo'] ?? 0] ?? ('Tipo ' . ($d['tipo'] ?? '')),
                $d['fechaEmision'] ?? '',
                $d['fechaVencimiento'] ?? '',
                $d['rut'] ?? '',
                $d['razonSocial'] ?? '',
                (float) ($d['montoNeto'] ?? 0),
                (float) ($d['montoExento'] ?? 0),
                (float) ($d['iva'] ?? 0),
                $total,
                $saldo,
                !empty($d['anulado']) ? 'SÍ' : '',
                count($cartolas),
                $pagado,
                $d['urlPDF'] ?? '',
            ], null, 'A' . $row);
            $row++;
        }
        // Totales
        $s1->setCellValue('E' . $row, 'TOTALES');
        $s1->setCellValue('J' . $row, $totFact);
        $s1->setCellValue('K' . $row, $totSaldo);
        $s1->setCellValue('N' . $row, $totPagado);
        $s1->getStyle('A' . $row . ':O' . $row)->applyFromArray($bold);
        foreach (['G', 'H', 'I', 'J', 'K', 'N'] as $c) {
            $s1->getStyle($c . '2:' . $c . $row)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'O') as $c) $s1->getColumnDimension($c)->setAutoSize(true);

        // ── Hoja 2: Conciliaciones (pagos recibidos) ───────────────────────────
        $s2 = $ss->createSheet();
        $s2->setTitle('Conciliaciones (pagos)');
        $s2->fromArray(['Folio factura', 'Fecha pago', 'Descripción', 'Monto aplicado a la factura', 'Monto total transferencia', 'Cuenta corriente'], null, 'A1');
        $s2->getStyle('A1:F1')->applyFromArray($hdrStyle);
        $r2 = 2;
        foreach ($encontrados as $d) {
            foreach (($d['Cartolas'] ?? []) as $c) {
                $s2->fromArray([
                    $d['folio'] ?? '',
                    $c['fecha'] ?? '',
                    $c['descripcion'] ?? '',
                    (float) ($c['CartolaDocumento']['monto'] ?? 0),
                    (float) ($c['abono'] ?? 0),
                    $c['idCuentaCorriente'] ?? '',
                ], null, 'A' . $r2);
                $r2++;
            }
        }
        $s2->getStyle('D2:E' . $r2)->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A', 'F') as $c) $s2->getColumnDimension($c)->setAutoSize(true);

        // ── Hoja 3: Resumen ────────────────────────────────────────────────────
        $s3 = $ss->createSheet();
        $s3->setTitle('Resumen');
        $s3->fromArray([
            ['Cliente', $q],
            ['Extraído de Chipax', now()->format('Y-m-d H:i')],
            ['Cantidad de documentos', count($encontrados)],
            ['Total facturado', $totFact],
            ['Total pagado (conciliado)', $totPagado],
            ['SALDO ADEUDADO', $totSaldo],
        ], null, 'A1');
        $s3->getStyle('A1:A6')->applyFromArray($bold);
        $s3->getStyle('B4:B6')->getNumberFormat()->setFormatCode('#,##0');
        $s3->getStyle('A6:B6')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'C0392B']]]);
        $s3->getColumnDimension('A')->setAutoSize(true);
        $s3->getColumnDimension('B')->setAutoSize(true);
        $ss->setActiveSheetIndex(2);

        $out = storage_path('app/EDIMAQ_Chipax_' . now()->format('Ymd') . '.xlsx');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($out);

        $this->newLine();
        $this->info("✅ Archivo generado: $out");
        $this->line("   Documentos: " . count($encontrados) . " | Facturado: $" . number_format($totFact, 0, ',', '.') . " | Pagado: $" . number_format($totPagado, 0, ',', '.') . " | ADEUDADO: $" . number_format($totSaldo, 0, ',', '.'));

        return 0;
    }
}
