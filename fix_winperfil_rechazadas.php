<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

// Estado Evaluación = id 1, Rechazada = id 3
$evaluacion = DB::table('estados_cotizacion')->where('nombre', 'Evaluación')->value('id');
$rechazada  = DB::table('estados_cotizacion')->where('nombre', 'Rechazada')->value('id');

echo "Estado Evaluación: {$evaluacion}\n";
echo "Estado Rechazada:  {$rechazada}\n";

$afectadas = DB::table('cotizaciones')
    ->whereNotNull('winperfil_numero')
    ->where('estado_cotizacion_id', $rechazada)
    ->count();

echo "Cotizaciones Winperfil en Rechazada: {$afectadas}\n";

$updated = DB::table('cotizaciones')
    ->whereNotNull('winperfil_numero')
    ->where('estado_cotizacion_id', $rechazada)
    ->update(['estado_cotizacion_id' => $evaluacion]);

echo "Actualizadas a Evaluación: {$updated}\n";

// Verificar
$post = DB::table('cotizaciones as c')
    ->join('estados_cotizacion as e', 'e.id', '=', 'c.estado_cotizacion_id')
    ->whereNotNull('c.winperfil_numero')
    ->select('e.nombre', DB::raw('COUNT(*) as n'))
    ->groupBy('e.nombre')
    ->get();
echo "\nEstado final:\n";
foreach ($post as $r) echo "  {$r->nombre}: {$r->n}\n";
