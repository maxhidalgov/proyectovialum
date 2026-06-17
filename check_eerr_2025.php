<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$desde = '2025-01-01';
$hasta = '2025-12-31';

echo "=== GASTOS EN EERR 2025 (SIN impuestos — criterio contable) ===\n";

$gastosBase = DB::table('gastos')
    ->whereBetween('fecha', [$desde, $hasta])
    ->where(function ($q) {
        $q->whereNull('chipax_tipo')->orWhereNotIn('chipax_tipo', ['impuesto']);
    });

$tot = (clone $gastosBase)->selectRaw('COUNT(*) as n, SUM(monto) as t')->first();
echo "Total: \$" . number_format($tot->t, 0, ',', '.') . " | {$tot->n} registros\n\n";

$cats = (clone $gastosBase)
    ->selectRaw("COALESCE(categoria, 'Sin categoría') as cat, COUNT(*) as n, SUM(monto) as t")
    ->groupBy('cat')->orderByDesc('t')->get();
foreach ($cats as $r) {
    echo "  [{$r->cat}] {$r->n} reg = \$" . number_format($r->t, 0, ',', '.') . "\n";
}

echo "\n=== IMPUESTOS (EXCLUIDOS del EERR) ===\n";
$imp = DB::table('gastos')->whereBetween('fecha', [$desde, $hasta])
    ->where('chipax_tipo', 'impuesto')
    ->selectRaw('COUNT(*) as n, SUM(monto) as t')->first();
echo "Excluidos: \$" . number_format($imp->t, 0, ',', '.') . " ({$imp->n} registros — IVA, PPM, retenciones)\n";

echo "\n=== REMUNERACIONES 2025 ===\n";
$remu = DB::table('pagos_empleado')->whereBetween('periodo', [$desde, $hasta])
    ->selectRaw('COUNT(*) as n, SUM(monto) as t')->first();
echo "Total: \$" . number_format($remu->t ?? 0, 0, ',', '.') . " | " . ($remu->n ?? 0) . " pagos\n";

echo "\n=== RESUMEN EERR 2025 ===\n";
$tg = (float)($tot->t ?? 0);
$tr = (float)($remu->t ?? 0);
$te = (float)DB::table('impuestos_chipax_placeholder')->sum('x') ?? 0; // placeholder
echo "Gastos operacionales (sin impuestos): \$" . number_format($tg, 0, ',', '.') . "\n";
echo "Remuneraciones:                       \$" . number_format($tr, 0, ',', '.') . "\n";
echo "TOTAL gastos+remu:                    \$" . number_format($tg + $tr, 0, ',', '.') . "\n";
echo "\nChipax EERR muestra:\n";
echo "  Sueldos (haberes brutos):  -\$240.523.017\n";
echo "  Nuestro EERR remuneraciones: -\$" . number_format($tr, 0, ',', '.') . " (líquido)\n";
echo "  Previred en gastos:          -\$" . number_format(DB::table('gastos')->whereBetween('fecha',[$desde,$hasta])->where('chipax_tipo','previred')->sum('monto'), 0, ',', '.') . "\n";
echo "  TOTAL costos laborales:      -\$" . number_format($tr + DB::table('gastos')->whereBetween('fecha',[$desde,$hasta])->where('chipax_tipo','previred')->sum('monto'), 0, ',', '.') . "\n";
