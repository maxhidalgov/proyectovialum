<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ChipaxApiService;
use Illuminate\Support\Facades\DB;

$api = new ChipaxApiService();

// 1. Contar gastos de Chipax por año (todas las páginas)
echo "=== GASTOS EN CHIPAX (todas las páginas) ===\n";
$page = 1; $total_pages = 1; $porAnio = [];
do {
    $data = $api->get('/gastos', ['page' => $page]);
    $items = $data['items'] ?? [];
    $pg = $data['paginationAttributes'] ?? [];
    if ($page === 1) { $total_pages = $pg['totalPages'] ?? 1; echo "Total: {$pg['count']}, Páginas: {$total_pages}\n"; }
    foreach ($items as $g) {
        $anio = substr($g['fecha'] ?? '0000', 0, 4);
        $porAnio[$anio] = ($porAnio[$anio] ?? 0) + 1;
    }
    $page++;
} while ($page <= $total_pages);
krsort($porAnio);
foreach ($porAnio as $a => $n) echo "  $a: $n gastos\n";

// 2. Contar en nuestra BD
echo "\n=== GASTOS EN NUESTRA BD ===\n";
$local = DB::table('gastos')->selectRaw('YEAR(fecha) as anio, COUNT(*) as n')->groupBy('anio')->orderByDesc('anio')->get();
foreach ($local as $r) echo "  {$r->anio}: {$r->n} gastos\n";

// 3. Endpoints adicionales: honorarios y previred para 2025
echo "\n=== HONORARIOS en Chipax (primera página) ===\n";
$hon = $api->get('/honorarios', ['page' => 1]);
$ph = $hon['paginationAttributes'] ?? [];
echo "Total: " . ($ph['count'] ?? '?') . ", Páginas: " . ($ph['totalPages'] ?? '?') . "\n";
$honorarios2025 = array_filter($hon['items'] ?? [], fn($h) => str_starts_with($h['fechaEmision'] ?? '', '2025'));
echo "Honorarios 2025 en primera página: " . count($honorarios2025) . "\n";
if (!empty($honorarios2025)) {
    $sample = array_values($honorarios2025)[0];
    echo "  Ejemplo: {$sample['nombreEmisor']} monto={$sample['montoBruto']}\n";
}

echo "\n=== PREVIRED en Chipax (primera página) ===\n";
try {
    $prev = $api->get('/previred', ['page' => 1]);
    $pp = $prev['paginationAttributes'] ?? [];
    echo "Total: " . ($pp['count'] ?? '?') . "\n";
    $items = $prev['items'] ?? $prev['docs'] ?? [];
    if (!empty($items)) { echo "Campos: " . implode(', ', array_keys((array)$items[0])) . "\n"; }
} catch (\Throwable $e) { echo "Error: " . $e->getMessage() . "\n"; }

echo "\n=== IMPUESTOS en Chipax (primera página) ===\n";
try {
    $imp = $api->get('/impuestos', ['page' => 1]);
    $ip = $imp['paginationAttributes'] ?? [];
    echo "Total: " . ($ip['count'] ?? '?') . "\n";
    $items = $imp['items'] ?? $imp['docs'] ?? [];
    if (!empty($items)) { echo "Campos: " . implode(', ', array_keys((array)$items[0])) . "\n"; }
} catch (\Throwable $e) { echo "Error: " . $e->getMessage() . "\n"; }
