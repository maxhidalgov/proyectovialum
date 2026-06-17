<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ChipaxApiService;
use Illuminate\Support\Facades\DB;

$api = new ChipaxApiService();

// 1. Ver estructura completa de un registro 2025 con items
echo "=== ESTRUCTURA COMPLETA de una remuneración 2025 ===\n";
$data = $api->get('/remuneraciones', ['page' => 1]);

// Encontrar primer 2025
$sample2025 = null;
foreach ($data['items'] ?? [] as $r) {
    if (substr($r['periodo'] ?? '', 0, 4) === '2025') {
        $sample2025 = $r;
        break;
    }
}
if (!$sample2025) {
    // Buscar en página 2
    $data2 = $api->get('/remuneraciones', ['page' => 2]);
    foreach ($data2['items'] ?? [] as $r) {
        if (substr($r['periodo'] ?? '', 0, 4) === '2025') { $sample2025 = $r; break; }
    }
}

if ($sample2025) {
    echo "periodo={$sample2025['periodo']} montoLiquido={$sample2025['montoLiquido']} montoPorPagar={$sample2025['montoPorPagar']}\n";
    echo "Empleado: " . ($sample2025['Empleado']['nombre'] ?? '?') . "\n";

    echo "\nItems (" . count($sample2025['items'] ?? []) . "):\n";
    foreach ($sample2025['items'] ?? [] as $item) {
        echo "  " . json_encode($item, JSON_UNESCAPED_UNICODE) . "\n";
    }

    echo "\nCategorias (" . count($sample2025['categorias'] ?? []) . "):\n";
    foreach ($sample2025['categorias'] ?? [] as $cat) {
        echo "  " . json_encode($cat, JSON_UNESCAPED_UNICODE) . "\n";
    }

    echo "\nCartolas: " . count($sample2025['Cartolas'] ?? []) . "\n";
    foreach ($sample2025['Cartolas'] ?? [] as $c) {
        echo "  cargo={$c['cargo']} abono={$c['abono']} desc={$c['descripcion']}\n";
    }
} else {
    echo "No se encontró registro 2025\n";
}

// 2. Totales 2025 usando montoLiquido vs monto de cartolas
echo "\n=== TOTALES 2025 DESDE CHIPAX ===\n";
$page = 1; $totalPages = 20;
$totalLiquido = 0; $totalCartolas = 0; $count2025 = 0;
$porMes = [];

do {
    $d = $api->get('/remuneraciones', ['page' => $page]);
    $pg = $d['paginationAttributes'] ?? [];
    if ($page === 1) $totalPages = (int)($pg['totalPages'] ?? 20);

    foreach ($d['items'] ?? [] as $rem) {
        $periodo = substr($rem['periodo'] ?? '0000-00', 0, 7);
        if (substr($periodo, 0, 4) !== '2025') continue;

        $count2025++;
        $liq = (float)($rem['montoLiquido'] ?? 0);
        $totalLiquido += $liq;

        // Sumar cartolas (cargo = lo que salió del banco)
        foreach ($rem['Cartolas'] ?? [] as $c) {
            $totalCartolas += (float)($c['cargo'] ?? 0);
        }

        $porMes[$periodo] = ($porMes[$periodo] ?? 0) + $liq;
    }
    $page++;
} while ($page <= $totalPages);

ksort($porMes);
echo "Registros 2025: {$count2025}\n";
echo "Total montoLiquido 2025: \$" . number_format($totalLiquido, 0, ',', '.') . "\n";
echo "Total salida banco (Cartolas cargo) 2025: \$" . number_format($totalCartolas, 0, ',', '.') . "\n";
echo "Nuestra BD (pagos_empleado): \$" . number_format(DB::table('pagos_empleado')->whereYear('periodo', 2025)->sum('monto'), 0, ',', '.') . "\n";

echo "\nPor mes (montoLiquido):\n";
foreach ($porMes as $m => $t) {
    $bd = DB::table('pagos_empleado')->whereYear('periodo', 2025)->whereRaw("DATE_FORMAT(periodo,'%Y-%m') = ?", [$m])->sum('monto');
    echo "  {$m}: Chipax=\$" . number_format($t, 0, ',', '.') . " | BD=\$" . number_format($bd, 0, ',', '.') . " | diff=\$" . number_format($t - $bd, 0, ',', '.') . "\n";
}
