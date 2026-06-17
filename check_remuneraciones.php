<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ChipaxApiService;
use Illuminate\Support\Facades\DB;

// 1. Ver lo que tenemos en BD por tipo
echo "=== PAGOS_EMPLEADO 2025 POR TIPO ===\n";
$tipos = DB::table('pagos_empleado')
    ->whereYear('periodo', 2025)
    ->selectRaw('tipo, COUNT(*) as n, SUM(monto) as total')
    ->groupBy('tipo')
    ->orderByDesc('total')
    ->get();
foreach ($tipos as $r) {
    echo "  [{$r->tipo}] {$r->n} registros = \$" . number_format($r->total, 0, ',', '.') . "\n";
}
$total = DB::table('pagos_empleado')->whereYear('periodo', 2025)->sum('monto');
echo "  TOTAL: \$" . number_format($total, 0, ',', '.') . "\n";

// 2. Muestra la estructura de un registro de Chipax /remuneraciones
echo "\n=== ESTRUCTURA CHIPAX /remuneraciones (primera página) ===\n";
$api = new ChipaxApiService();
$data = $api->get('/remuneraciones', ['page' => 1]);
$pg = $data['paginationAttributes'] ?? [];
echo "Total Chipax: " . ($pg['count'] ?? '?') . " | Páginas: " . ($pg['totalPages'] ?? '?') . "\n";

$items = $data['items'] ?? [];
if (!empty($items)) {
    $sample = $items[0];
    echo "Campos top-level: " . implode(', ', array_keys($sample)) . "\n";

    // Ver categorias
    $cats = $sample['categorias'] ?? [];
    if (!empty($cats)) {
        echo "Categorías del primer registro:\n";
        foreach ($cats as $cat) {
            echo "  - " . json_encode($cat, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }

    // Ver Cartolas
    $cartolas = $sample['Cartolas'] ?? [];
    echo "Cartolas en primer registro: " . count($cartolas) . "\n";
    if (!empty($cartolas)) {
        echo "  Cartola[0]: " . json_encode($cartolas[0], JSON_UNESCAPED_UNICODE) . "\n";
    }

    // Campos numéricos clave
    echo "Campos numéricos: haberes=" . ($sample['haberes'] ?? '?')
        . " descuentos=" . ($sample['descuentos'] ?? '?')
        . " liquido=" . ($sample['liquido'] ?? '?')
        . " periodo=" . ($sample['periodo'] ?? '?') . "\n";
}

// 3. Revisar TODAS las páginas para ver totales por categoría
echo "\n=== TOTALES CHIPAX /remuneraciones POR CATEGORÍA (todas las páginas) ===\n";
$page = 1; $totalPages = (int)($pg['totalPages'] ?? 1);
$porCategoria = []; $totalLiquido = 0; $totalHaberes = 0; $conteo2025 = 0;

do {
    $data2 = ($page === 1) ? $data : $api->get('/remuneraciones', ['page' => $page]);
    foreach ($data2['items'] ?? [] as $rem) {
        $periodo = substr($rem['periodo'] ?? '0000-00', 0, 7);
        $anio = substr($periodo, 0, 4);
        if ($anio !== '2025') { $page++; continue 2; }

        $conteo2025++;
        $liquido = (float)($rem['liquido'] ?? 0);
        $haberes = (float)($rem['haberes'] ?? 0);
        $totalLiquido += $liquido;
        $totalHaberes += $haberes;

        foreach ($rem['categorias'] ?? [] as $cat) {
            $nombre = $cat['nombre'] ?? $cat['name'] ?? 'Sin nombre';
            $monto = (float)($cat['monto'] ?? $cat['amount'] ?? 0);
            $porCategoria[$nombre] = ($porCategoria[$nombre] ?? 0) + $monto;
        }
    }
    $page++;
} while ($page <= $totalPages);

echo "Registros 2025 en Chipax: {$conteo2025}\n";
echo "Total líquido 2025: \$" . number_format($totalLiquido, 0, ',', '.') . "\n";
echo "Total haberes 2025: \$" . number_format($totalHaberes, 0, ',', '.') . "\n";
arsort($porCategoria);
echo "Por categoría:\n";
foreach ($porCategoria as $nombre => $monto) {
    echo "  [{$nombre}] \$" . number_format($monto, 0, ',', '.') . "\n";
}

// 4. Muestra estructura de Cartolas en primeros 3 registros 2025
echo "\n=== EJEMPLO CARTOLAS en remun 2025 ===\n";
$data3 = $api->get('/remuneraciones', ['page' => 1]);
$ej = 0;
foreach ($data3['items'] ?? [] as $rem) {
    if (substr($rem['periodo'] ?? '', 0, 4) !== '2025') continue;
    if ($ej >= 2) break;
    echo "  periodo={$rem['periodo']} liquido={$rem['liquido']} haberes={$rem['haberes']}\n";
    foreach ($rem['Cartolas'] ?? [] as $c) {
        echo "    Cartola: " . json_encode($c, JSON_UNESCAPED_UNICODE) . "\n";
    }
    $ej++;
}
