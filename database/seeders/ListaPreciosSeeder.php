<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;
use App\Models\ListaPrecio;
use App\Models\ProductoColorProveedor;
use Carbon\Carbon;

class ListaPreciosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "🔄 Creando lista de precios para todos los productos desde producto_color_proveedor...\n";
        
        // Obtener todas las combinaciones de producto-color-proveedor
        $productosColorProveedor = ProductoColorProveedor::with(['producto', 'color', 'proveedor'])->get();
        
        if ($productosColorProveedor->count() === 0) {
            echo "⚠️ No hay registros en producto_color_proveedor. No se pueden crear listas de precios.\n";
            return;
        }
        
        $creados = 0;
        $actualizados = 0;
        
        foreach ($productosColorProveedor as $pcp) {
            if (!$pcp->producto) {
                echo "⚠️ Producto no encontrado para ID {$pcp->producto_id}, saltando...\n";
                continue;
            }
            
            // Verificar si ya existe una lista de precios para esta combinación
            $listaPrecioExistente = ListaPrecio::where('producto_id', $pcp->producto_id)
                ->where('producto_color_proveedor_id', $pcp->id)
                ->first();
            
            // Usar el costo real de la tabla producto_color_proveedor
            $precioCosto = $pcp->costo ?? 0;
            
            if ($precioCosto <= 0) {
                $colorNombre = $pcp->color ? $pcp->color->nombre : 'N/A';
                $proveedorNombre = $pcp->proveedor ? $pcp->proveedor->nombre : 'N/A';
                echo "⚠️ Producto '{$pcp->producto->nombre}' - Color: {$colorNombre} - Proveedor: {$proveedorNombre} tiene costo 0 o negativo, saltando...\n";
                continue;
            }
            
            // Margen por defecto 45% (como el de las ventanas)
            $margen = 45;
            
            // Calcular precio de venta
            $precioVenta = $precioCosto * (1 + $margen / 100);
            
            if ($listaPrecioExistente) {
                // Actualizar si existe
                $listaPrecioExistente->update([
                    'precio_costo' => $precioCosto,
                    'margen' => $margen,
                    'precio_venta' => $precioVenta,
                    'vigencia_desde' => Carbon::now(),
                    'vigencia_hasta' => Carbon::now()->addYear(),
                    'activo' => 1
                ]);
                $actualizados++;
                $colorNombre = $pcp->color ? $pcp->color->nombre : 'N/A';
                $proveedorNombre = $pcp->proveedor ? $pcp->proveedor->nombre : 'N/A';
                echo "✏️ Lista actualizada: '{$pcp->producto->nombre}' - {$colorNombre} - {$proveedorNombre} - Costo: \${$precioCosto} - Venta: \${$precioVenta}\n";
            } else {
                // Crear nueva
                ListaPrecio::create([
                    'producto_id' => $pcp->producto_id,
                    'producto_color_proveedor_id' => $pcp->id,
                    'precio_costo' => $precioCosto,
                    'margen' => $margen,
                    'precio_venta' => $precioVenta,
                    'vigencia_desde' => Carbon::now(),
                    'vigencia_hasta' => Carbon::now()->addYear(),
                    'activo' => 1
                ]);
                $creados++;
                $colorNombre = $pcp->color ? $pcp->color->nombre : 'N/A';
                $proveedorNombre = $pcp->proveedor ? $pcp->proveedor->nombre : 'N/A';
                echo "✅ Lista creada: '{$pcp->producto->nombre}' - {$colorNombre} - {$proveedorNombre} - Costo: \${$precioCosto} - Venta: \${$precioVenta}\n";
            }
        }
        
        echo "\n🎉 Seeder completado!\n";
        echo "📊 Listas creadas: {$creados}\n";
        echo "📊 Listas actualizadas: {$actualizados}\n";
        echo "📊 Total en base de datos: " . ListaPrecio::count() . "\n";
    }
}
