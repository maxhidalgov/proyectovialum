<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== COTIZACIONES WINPERFIL POR ESTADO ===\n";
$rows = DB::table('cotizaciones as c')
    ->join('estados_cotizacion as e', 'e.id', '=', 'c.estado_cotizacion_id')
    ->whereNotNull('c.winperfil_numero')
    ->select('e.nombre', DB::raw('COUNT(*) as n'))
    ->groupBy('e.nombre')
    ->get();
foreach ($rows as $r) {
    echo "  {$r->nombre}: {$r->n}\n";
}

echo "\n=== ESTADOS DISPONIBLES ===\n";
$estados = DB::table('estados_cotizacion')->get(['id', 'nombre']);
foreach ($estados as $e) {
    echo "  [{$e->id}] {$e->nombre}\n";
}
