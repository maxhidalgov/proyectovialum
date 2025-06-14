<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\ProductoColorProveedor;
use Illuminate\Support\Facades\Log;

class ImportacionController extends Controller
{
    // Etapa 1: Importar productos
    public function importarProductos()
    {
        $file = storage_path('app/productos.csv');

        if (!file_exists($file)) {
            return response()->json(['error' => 'Archivo productos.csv no encontrado'], 404);
        }

        $handle = fopen($file, 'r');
        fgetcsv($handle); // Saltar cabecera

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            Producto::create([
                'nombre' => $data[0],
                'tipo_producto_id' => $data[1],
                'largo_total' => $data[2],
                'peso_por_metro' => $data[3],
                'unidad_id' => $data[4],
            ]);
        }

        fclose($handle);

        return response()->json(['message' => 'Productos importados correctamente']);
    }

public function importarProductoColorProveedor()
{
    $file = storage_path('app/producto_color_proveedor.csv');

    if (!file_exists($file)) {
        return response()->json(['error' => 'Archivo producto_color_proveedor.csv no encontrado'], 404);
    }

    $handle = fopen($file, 'r');
    $header = fgetcsv($handle); // Saltar cabecera

    $errores = [];
    $importadas = 0;

    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
        // Validación mínima: asegurarse de que hay al menos 6 columnas
        if (count($data) < 6) {
            $errores[] = "Fila incompleta: " . implode(',', $data);
            continue;
        }

        // Validación de tipos numéricos
        if (!is_numeric($data[0]) || !is_numeric($data[1]) || !is_numeric($data[2]) || !is_numeric($data[3])) {
            $errores[] = "Valores numéricos inválidos: " . implode(',', $data);
            continue;
        }

        try {
            ProductoColorProveedor::create([
                'producto_id' => (int) $data[0],
                'color_id' => (int) $data[1],
                'proveedor_id' => (int) $data[2],
                'costo' => (float) $data[3],
                'codigo_proveedor' => trim($data[4]),
                'stock' => isset($data[5]) && is_numeric($data[5]) ? (int) $data[5] : 0,
            ]);

            $importadas++;
        } catch (\Exception $e) {
            $errores[] = "Error al insertar fila: " . implode(',', $data) . " → " . $e->getMessage();
        }
    }

    fclose($handle);

    return response()->json([
        'importadas' => $importadas,
        'errores' => $errores,
        'message' => 'Proceso de importación finalizado'
    ]);
}

}
