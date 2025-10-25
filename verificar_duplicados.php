<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Verificando duplicados en lista_precios...\n\n";

// Ver duplicados
$duplicados = DB::select("
    SELECT 
        lp.producto_id,
        lp.color_id,
        p.nombre AS producto,
        c.nombre AS color,
        COUNT(*) AS cantidad,
        GROUP_CONCAT(lp.id ORDER BY lp.precio_costo DESC) AS ids,
        GROUP_CONCAT(lp.precio_costo ORDER BY lp.precio_costo DESC) AS costos
    FROM lista_precios lp
    LEFT JOIN productos p ON lp.producto_id = p.id
    LEFT JOIN colores c ON lp.color_id = c.id
    GROUP BY lp.producto_id, lp.color_id
    HAVING COUNT(*) > 1
");

if (empty($duplicados)) {
    echo "✅ No hay duplicados en lista_precios\n";
} else {
    echo "❌ Duplicados encontrados:\n\n";
    foreach ($duplicados as $dup) {
        echo "Producto: {$dup->producto} | Color: {$dup->color}\n";
        echo "IDs: {$dup->ids}\n";
        echo "Costos: {$dup->costos}\n";
        echo "Cantidad: {$dup->cantidad}\n";
        echo "---\n";
    }
}

// Verificar si existe el índice único
echo "\n🔍 Verificando índice único...\n";
$indices = DB::select("SHOW INDEX FROM lista_precios WHERE Key_name = 'unique_producto_color'");

if (empty($indices)) {
    echo "❌ El índice único NO existe\n";
} else {
    echo "✅ El índice único SÍ existe\n";
}

// Ver estructura de la tabla
echo "\n📋 Estructura de lista_precios:\n";
$columnas = DB::select("DESCRIBE lista_precios");
foreach ($columnas as $col) {
    if (in_array($col->Field, ['color_id', 'proveedor_sugerido_id', 'producto_color_proveedor_id'])) {
        echo "  ✓ {$col->Field} ({$col->Type}) - Null: {$col->Null}\n";
    }
}
