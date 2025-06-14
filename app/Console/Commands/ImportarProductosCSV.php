<?php

// app/Http/Controllers/ImportacionController.php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoColorProveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImportacionController extends Controller
{
public function importarProductosDesdeCSV()
{
    $productosCSV = storage_path('app/productos.csv');
    $productos = [];

    if (($handle = fopen($productosCSV, 'r')) !== false) {
        fgetcsv($handle); // Saltar cabecera
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $producto = Producto::create([
                'nombre' => $data[0],
                'tipo_producto_id' => $data[1],
                'largo_total' => $data[2],
                'peso_por_metro' => $data[3],
                'unidad_id' => $data[4],
            ]);
            $productos[$producto->nombre] = $producto->id;
        }
        fclose($handle);
    }

    $combinacionesCSV = storage_path('app/producto_color_proveedor.csv');

    if (($handle = fopen($combinacionesCSV, 'r')) !== false) {
        fgetcsv($handle); // Saltar cabecera
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $nombreProducto = trim($data[0]);
            $producto_id = $productos[$nombreProducto] ?? null;

            if ($producto_id) {
                ProductoColorProveedor::create([
                    'producto_id' => $producto_id,
                    'color_id' => $data[1],
                    'proveedor_id' => $data[2],
                    'costo' => $data[3],
                    'codigo_proveedor' => $data[4],
                    'stock' => $data[5] ?? 0,
                ]);
            } else {
                Log::warning("Producto no encontrado para la combinación: $nombreProducto");
            }
        }
        fclose($handle);
    }

    return response()->json(['success' => true]);
}
}

