<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use Illuminate\Support\Facades\Log;
use App\Models\ProductoColorProveedor;

class ProductoController extends Controller
{
    public function index()
    {
        return Producto::with([
            'coloresPorProveedor.proveedor',
            'coloresPorProveedor.color',
            'unidad',
            'tipoProducto'
        ])->get();
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'unidad_id' => 'nullable|exists:unidades,id',
            'largo_total' => 'nullable|numeric',
            'peso_por_metro' => 'nullable|numeric',
            'tipo_producto_id' => 'nullable|exists:tipos_producto,id',
            'producto_color_proveedor' => 'nullable|array',
            'producto_color_proveedor.*.proveedor_id' => 'required|exists:proveedors,id',
            'producto_color_proveedor.*.color_id' => 'required|exists:colores,id',
            'producto_color_proveedor.*.costo' => 'required|numeric|min:0',
            'producto_color_proveedor.*.codigo_proveedor' => 'nullable|string'
        ]);

        try {
            $producto = Producto::create([
                'nombre' => $validatedData['nombre'],
                'tipo_producto_id' => $validatedData['tipo_producto_id'] ?? null,
                'unidad_id' => $validatedData['unidad_id'] ?? null,
                'largo_total' => $validatedData['largo_total'] ?? null,
                'peso_por_metro' => $validatedData['peso_por_metro'] ?? null,
                
            ]);

            if (!empty($request->producto_color_proveedor)) {
                foreach ($request->producto_color_proveedor as $combo) {
                    ProductoColorProveedor::create([
                        'producto_id' => $producto->id,
                        'proveedor_id' => $combo['proveedor_id'],
                        'color_id' => $combo['color_id'],
                        'costo' => $combo['costo'],
                        'codigo_proveedor' => $combo['codigo_proveedor'] ?? null,
                        'stock' => 0,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Producto y combinaciones creados exitosamente',
                'data' => $producto
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear producto: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $producto = Producto::with(['coloresPorProveedor.proveedor', 'coloresPorProveedor.color'])->find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        return response()->json($producto, 200);
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'unidad_id' => 'nullable|exists:unidades,id',
            'largo_total' => 'nullable|numeric',
            'peso_por_metro' => 'nullable|numeric',
            'tipo_producto_id' => 'nullable|exists:tipos_producto,id',
            'producto_color_proveedor' => 'nullable|array',
            'producto_color_proveedor.*.proveedor_id' => 'required|exists:proveedors,id',
            'producto_color_proveedor.*.color_id' => 'required|exists:colores,id',
            'producto_color_proveedor.*.costo' => 'required|numeric|min:0',
            'producto_color_proveedor.*.codigo_proveedor' => 'nullable|string'
        ]);

        try {
            $producto->update([
                'nombre' => $validatedData['nombre'],
                'tipo_producto_id' => $validatedData['tipo_producto_id'] ?? null,
                'unidad_id' => $validatedData['unidad_id'] ?? null,
                'largo_total' => $validatedData['largo_total'] ?? null,
                'peso_por_metro' => $validatedData['peso_por_metro'] ?? null,
                
            ]);

            if (!empty($validatedData['producto_color_proveedor'])) {
                ProductoColorProveedor::where('producto_id', $producto->id)->delete();

                foreach ($validatedData['producto_color_proveedor'] as $combo) {
                    ProductoColorProveedor::create([
                        'producto_id' => $producto->id,
                        'proveedor_id' => $combo['proveedor_id'],
                        'color_id' => $combo['color_id'],
                        'costo' => $combo['costo'],
                        'codigo_proveedor' => $combo['codigo_proveedor'] ?? null,
                        'stock' => 0,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Producto actualizado correctamente',
                'data' => $producto
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        try {
            $producto->delete();

            return response()->json([
                'message' => 'Producto eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar producto: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getProveedoresPorProductoYColor($productoId, $colorId)
{
    $proveedores = \App\Models\ProductoColorProveedor::with('proveedor')
        ->where('producto_id', $productoId)
        ->where('color_id', $colorId)
        ->get();

    return response()->json($proveedores);
}
}
