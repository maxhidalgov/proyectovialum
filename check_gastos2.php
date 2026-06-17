<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ChipaxApiService;

$api = new ChipaxApiService();

// ---- HONORARIOS ----
echo "=== HONORARIOS (todas las páginas) ===\n";
$page = 1; $total_pages = 1; $hon2025 = []; $honTotal = 0;
do {
    $data = $api->get('/honorarios', ['page' => $page]);
    $pg = $data['paginationAttributes'] ?? [];
    if ($page === 1) { $total_pages = $pg['totalPages'] ?? 1; echo "Total registros: {$pg['count']}, Páginas: {$total_pages}\n"; }
    foreach ($data['items'] ?? [] as $h) {
        $fecha = $h['fechaEmision'] ?? $h['fecha'] ?? '0000-00-00';
        $anio = substr($fecha, 0, 4);
        if ($anio === '2025') {
            $hon2025[] = $h;
        }
        $honTotal += (float)($h['montoBruto'] ?? $h['monto'] ?? 0);
    }
    $page++;
} while ($page <= $total_pages);

$sum2025 = array_sum(array_column(array_map(fn($h) => ['m' => (float)($h['montoBruto'] ?? $h['monto'] ?? 0)], $hon2025), 'm'));
echo "Honorarios 2025: " . count($hon2025) . " registros, Monto: $" . number_format($sum2025, 0, ',', '.') . "\n";
echo "Campos disponibles: " . (!empty($hon2025) ? implode(', ', array_keys($hon2025[0])) : 'N/A') . "\n";
if (!empty($hon2025)) {
    echo "Ejemplo: " . json_encode(array_slice($hon2025[0], 0, 10), JSON_UNESCAPED_UNICODE) . "\n";
}

// ---- PREVIRED ----
echo "\n=== PREVIRED (todas las páginas) ===\n";
$page = 1; $total_pages = 1; $prev2025 = []; $prevFields = [];
do {
    $data = $api->get('/previred', ['page' => $page]);
    $pg = $data['paginationAttributes'] ?? [];
    if ($page === 1) { $total_pages = $pg['totalPages'] ?? 1; echo "Total: {$pg['count']}, Páginas: {$total_pages}\n"; }
    $items = $data['items'] ?? $data['docs'] ?? [];
    if (empty($prevFields) && !empty($items)) $prevFields = array_keys((array)$items[0]);
    foreach ($items as $p) {
        $periodo = $p['periodo'] ?? '0000-00';
        $anio = substr($periodo, 0, 4);
        if ($anio === '2025') $prev2025[] = $p;
    }
    $page++;
} while ($page <= $total_pages);

$sumPrev2025 = array_sum(array_map(fn($p) => (float)($p['monto'] ?? 0), $prev2025));
echo "Previred 2025: " . count($prev2025) . " registros, Monto: $" . number_format($sumPrev2025, 0, ',', '.') . "\n";
echo "Campos: " . implode(', ', $prevFields) . "\n";
if (!empty($prev2025)) {
    $sample = $prev2025[0];
    $cartolas = $sample['Cartolas'] ?? [];
    echo "Ejemplo periodo={$sample['periodo']} monto={$sample['monto']} cartolas=" . count($cartolas) . "\n";
    if (!empty($cartolas)) echo "  Cartola[0]: " . json_encode($cartolas[0], JSON_UNESCAPED_UNICODE) . "\n";
}

// ---- IMPUESTOS ----
echo "\n=== IMPUESTOS (todas las páginas) ===\n";
$page = 1; $total_pages = 1; $imp2025 = []; $impFields = [];
do {
    $data = $api->get('/impuestos', ['page' => $page]);
    $pg = $data['paginationAttributes'] ?? [];
    if ($page === 1) { $total_pages = $pg['totalPages'] ?? 1; echo "Total: {$pg['count']}, Páginas: {$total_pages}\n"; }
    $items = $data['items'] ?? $data['docs'] ?? [];
    if (empty($impFields) && !empty($items)) $impFields = array_keys((array)$items[0]);
    foreach ($items as $i) {
        $periodo = $i['periodo'] ?? '0000-00';
        $anio = substr($periodo, 0, 4);
        if ($anio === '2025') $imp2025[] = $i;
    }
    $page++;
} while ($page <= $total_pages);

$sumImp2025 = array_sum(array_map(fn($i) => (float)($i['monto'] ?? 0), $imp2025));
echo "Impuestos 2025: " . count($imp2025) . " registros, Monto: $" . number_format($sumImp2025, 0, ',', '.') . "\n";
echo "Campos: " . implode(', ', $impFields) . "\n";
if (!empty($imp2025)) {
    $sample = $imp2025[0];
    $cartolas = $sample['Cartolas'] ?? [];
    echo "Ejemplo periodo={$sample['periodo']} monto={$sample['monto']} idTipoImpuesto=" . ($sample['idTipoImpuesto'] ?? '?') . " cartolas=" . count($cartolas) . "\n";
    if (!empty($cartolas)) echo "  Cartola[0]: " . json_encode($cartolas[0], JSON_UNESCAPED_UNICODE) . "\n";
}

// ---- GASTOS 2025 desde nuestra BD con montos ----
echo "\n=== GASTOS 2025 EN BD (con montos) ===\n";
use Illuminate\Support\Facades\DB;
$g2025 = DB::table('gastos')
    ->whereYear('fecha', 2025)
    ->selectRaw('categoria, COUNT(*) as n, SUM(monto) as total')
    ->groupBy('categoria')
    ->orderByDesc('total')
    ->get();
$grandTotal = 0;
foreach ($g2025 as $r) {
    echo "  [{$r->categoria}] {$r->n} gastos = $" . number_format($r->total, 0, ',', '.') . "\n";
    $grandTotal += $r->total;
}
echo "  TOTAL: $" . number_format($grandTotal, 0, ',', '.') . "\n";
