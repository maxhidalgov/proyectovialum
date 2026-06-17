<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$desde = '2025-01-01'; $hasta = '2025-12-31';

// Gastos: sin impuesto NI previred
$gastos = DB::table('gastos')->whereBetween('fecha',[$desde,$hasta])
    ->where(function($q){ $q->whereNull('chipax_tipo')->orWhereNotIn('chipax_tipo',['impuesto','previred']); })
    ->selectRaw("COALESCE(categoria,'Sin categoría') as cat, SUM(monto) as t")
    ->groupBy('cat')->orderByDesc('t')->get();
$totalGastos = $gastos->sum('t');

// Remuneraciones: sueldos + previred
$sueldos   = DB::table('pagos_empleado')->whereBetween('periodo',[$desde,$hasta])->sum('monto');
$previred  = DB::table('gastos')->whereBetween('fecha',[$desde,$hasta])->where('chipax_tipo','previred')->sum('monto');
$totalRemu = $sueldos + $previred;

echo "=== GASTOS OPERACIONALES 2025 ===\n";
foreach ($gastos as $r) echo "  [{$r->cat}] \$" . number_format($r->t,0,',','.') . "\n";
echo "  TOTAL GASTOS: \$" . number_format($totalGastos,0,',','.') . "\n";

echo "\n=== REMUNERACIONES 2025 ===\n";
echo "  Sueldos líquidos:    \$" . number_format($sueldos,0,',','.') . "\n";
echo "  Previred (cotiz.):   \$" . number_format($previred,0,',','.') . "\n";
echo "  TOTAL REMUNERACIONES: \$" . number_format($totalRemu,0,',','.') . "\n";

echo "\n=== EXCLUIDOS DEL EERR ===\n";
$imp = DB::table('gastos')->whereBetween('fecha',[$desde,$hasta])->where('chipax_tipo','impuesto')->sum('monto');
echo "  Impuestos (IVA/PPM/ret.): \$" . number_format($imp,0,',','.') . "\n";

echo "\n=== Chipax EERR referencia ===\n";
echo "  Sueldos Chipax: \$240.523.017 (haberes brutos = líquido + cotiz. empleado)\n";
echo "  Nuestra remu:   \$" . number_format($totalRemu,0,',','.') . " (diff \$" . number_format(240523017 - $totalRemu,0,',','.') . " = cotiz. empleado en AFP/salud)\n";
