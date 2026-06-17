<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== GASTOS 2025 POR TIPO ===\n";
$rows = DB::table('gastos')
    ->whereYear('fecha', 2025)
    ->selectRaw("COALESCE(chipax_tipo, 'manual') as tipo, COUNT(*) as n, SUM(monto) as total")
    ->groupBy('tipo')
    ->orderByDesc('total')
    ->get();
foreach ($rows as $r) {
    echo "  {$r->tipo}: {$r->n} registros, \$" . number_format($r->total, 0, ',', '.') . "\n";
}
$total = DB::table('gastos')->whereYear('fecha', 2025)->sum('monto');
echo "TOTAL 2025: \$" . number_format($total, 0, ',', '.') . "\n";

echo "\n=== GASTOS 2025 POR CATEGORIA ===\n";
$cats = DB::table('gastos')
    ->whereYear('fecha', 2025)
    ->selectRaw("COALESCE(categoria, 'Sin categoría') as cat, COUNT(*) as n, SUM(monto) as total")
    ->groupBy('cat')
    ->orderByDesc('total')
    ->get();
foreach ($cats as $r) {
    echo "  {$r->cat}: {$r->n} reg = \$" . number_format($r->total, 0, ',', '.') . "\n";
}

echo "\n=== PREVIRED 2025 DETALLE ===\n";
$prev = DB::table('gastos')
    ->where('chipax_tipo', 'previred')
    ->whereYear('fecha', 2025)
    ->orderBy('fecha')
    ->get(['fecha', 'descripcion', 'monto', 'pagado_historico']);
foreach ($prev as $r) {
    echo "  {$r->fecha} | {$r->descripcion} | \$" . number_format($r->monto, 0, ',', '.') . " | hist={$r->pagado_historico}\n";
}
