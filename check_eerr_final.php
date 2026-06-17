<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$desde = '2025-01-01';
$hasta = '2025-12-31';

$gastosSinImp = DB::table('gastos')
    ->whereBetween('fecha', [$desde, $hasta])
    ->where(function ($q) {
        $q->whereNull('chipax_tipo')->orWhereNotIn('chipax_tipo', ['impuesto']);
    })
    ->selectRaw('COUNT(*) as n, SUM(monto) as t')->first();

$impExcluidos = DB::table('gastos')
    ->whereBetween('fecha', [$desde, $hasta])
    ->where('chipax_tipo', 'impuesto')
    ->selectRaw('COUNT(*) as n, SUM(monto) as t')->first();

$previred = DB::table('gastos')
    ->whereBetween('fecha', [$desde, $hasta])
    ->where('chipax_tipo', 'previred')
    ->sum('monto');

$remu = DB::table('pagos_empleado')
    ->whereBetween('periodo', [$desde, $hasta])
    ->sum('monto');

echo "=== EERR 2025 (criterio contable corregido) ===\n";
echo "GASTOS operacionales (sin impuestos): \$" . number_format($gastosSinImp->t, 0, ',', '.') . " ({$gastosSinImp->n} regs)\n";
echo "  - Sin categoría (op.):  \$" . number_format(DB::table('gastos')->whereBetween('fecha',[$desde,$hasta])->whereNull('categoria')->sum('monto'),0,',','.') . "\n";
echo "  - Previred:             \$" . number_format($previred,0,',','.') . "\n";
echo "  - Honorarios:           \$" . number_format(DB::table('gastos')->whereBetween('fecha',[$desde,$hasta])->where('chipax_tipo','honorario')->sum('monto'),0,',','.') . "\n";
echo "\nIMPUESTOS excluidos del EERR: \$" . number_format($impExcluidos->t, 0, ',', '.') . " ({$impExcluidos->n} regs — IVA/PPM/ret.)\n";
echo "\nREMUNERACIONES (montoLíquido): \$" . number_format($remu, 0, ',', '.') . "\n";
echo "Costos laborales totales:      \$" . number_format($remu + $previred, 0, ',', '.') . " (remu+previred)\n";
echo "\nChipax EERR muestra Sueldos = \$240.523.017 (incluye haberes brutos+cotiz.)\n";
echo "Nuestra separación:  remu líquido \$" . number_format($remu,0,',','.') . " + previred \$" . number_format($previred,0,',','.') . " = \$" . number_format($remu+$previred,0,',','.') . "\n";
