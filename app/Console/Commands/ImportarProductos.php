<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Producto;
use App\Models\ProductoColorProveedor;
use Illuminate\Support\Facades\Storage;

class ImportarProductos extends Command
{
    protected $signature = 'importar:productos';
    protected $description = 'Importa productos y sus combinaciones desde un archivo CSV';

    public function handle()
    {
        $path = storage_path('app/productos.csv');

        if (!file_exists($path)) {
            $this->error('Archivo productos.csv no encontrado en storage/app/');
            return 1;
        }

        $file = fopen($path, 'r');
        $header = fgetcsv($file); // Leer encabezados

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            $producto = Producto::firstOrCreate(
                ['codigo' => $data['codigo']],
                [
                    'nombre' => $data['nombre_producto'],
                    'tipo_producto_id' => $data['tipo_producto_id'],
                    'largo_total' => $data['largo_total'] ?: null,
                    'peso_por_metro' => $data['peso_por_metro'] ?: null,
                    'unidad_id' => $data['unidad_id'],
                    'codigo' => $data['codigo'],
                ]
            );

            ProductoColorProveedor::updateOrCreate(
                [
                    'producto_id' => $producto->id,
                    'color_id' => $data['color_id'],
                    'proveedor_id' => $data['proveedor_id'],
                ],
                [
                    'costo' => $data['costo'],
                ]
            );
        }

        fclose($file);

        $this->info('✅ Productos importados exitosamente.');
        return 0;
    }
}
