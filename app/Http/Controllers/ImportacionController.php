<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\ProductoColorProveedor;
use Illuminate\Support\Facades\Log;

class ImportacionController extends Controller
{
    // Etapa 1: Importar productos
    public function importarProductos(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('file')->getRealPath();

        $handle = fopen($path, 'r');
        fgetcsv($handle); // Saltar cabecera

        $importadas = 0;

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            Producto::create([
                'nombre' => $data[0],
                'tipo_producto_id' => $data[1],
                'largo_total' => $data[2],
                'peso_por_metro' => $data[3],
                'unidad_id' => $data[4],
            ]);
            $importadas++;
        }

        fclose($handle);

        return response()->json(['message' => "Productos importados correctamente ($importadas)"]);
    }

    public function importarProductoColorProveedor(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('file')->getRealPath();

        $handle = fopen($path, 'r');
        fgetcsv($handle); // Saltar cabecera

        $errores = [];
        $importadas = 0;

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if (count($data) < 6) {
                $errores[] = "Fila incompleta: " . implode(',', $data);
                continue;
            }

            if (!is_numeric($data[0]) || !is_numeric($data[1]) || !is_numeric($data[2]) || !is_numeric($data[4])) {
                $errores[] = "Valores numéricos inválidos: " . implode(',', $data);
                continue;
            }

            try {
                ProductoColorProveedor::create([
                    'producto_id' => (int) $data[0],
                    'proveedor_id' => (int) $data[1],
                    'color_id' => (int) $data[2],
                    'codigo_proveedor' => trim($data[3]),
                    'costo' => (float) $data[4],
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
