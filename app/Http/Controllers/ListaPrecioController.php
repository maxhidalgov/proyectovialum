<?php

namespace App\Http\Controllers;

use App\Models\ListaPrecio;
use App\Models\Producto;
use App\Models\ProductoColorProveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ListaPrecioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = ListaPrecio::with([
                'producto.tipoProducto',
                'producto.unidad',
                'productoColorProveedor.color',
                'productoColorProveedor.proveedor'
            ]);

            // Filtros opcionales
            if ($request->has('activo')) {
                $query->where('activo', $request->activo);
            }

            if ($request->has('producto_id')) {
                $query->where('producto_id', $request->producto_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('producto', function($q) use ($search) {
                    $q->where('nombre', 'LIKE', "%{$search}%");
                });
            }

            $listaPrecios = $query->with([
                'producto.tipoProducto',
                'productoColorProveedor.color',
                'productoColorProveedor.proveedor'
            ])->orderBy('created_at', 'desc')->get();

            return response()->json($listaPrecios);
        } catch (\Exception $e) {
            Log::error('Error al cargar lista de precios: ' . $e->getMessage());
            return response()->json(['error' => 'Error al cargar lista de precios'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'producto_id' => 'required|exists:productos,id',
                'producto_color_proveedor_id' => 'required|exists:producto_color_proveedor,id',
                'precio_costo' => 'required|numeric|min:0',
                'margen' => 'required|numeric|min:0|max:100',
                'vigencia_desde' => 'nullable|date',
                'vigencia_hasta' => 'nullable|date|after:vigencia_desde',
                'activo' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $precioCosto = floatval($request->precio_costo);
            $margen = floatval($request->margen);
            $precioVenta = $precioCosto * (1 + $margen / 100);

            $listaPrecio = ListaPrecio::create([
                'producto_id' => $request->producto_id,
                'producto_color_proveedor_id' => $request->producto_color_proveedor_id,
                'precio_costo' => $precioCosto,
                'margen' => $margen,
                'precio_venta' => $precioVenta,
                'vigencia_desde' => $request->vigencia_desde ?? now(),
                'vigencia_hasta' => $request->vigencia_hasta ?? now()->addYear(),
                'activo' => $request->activo ?? true
            ]);

            $listaPrecio->load([
                'producto.tipoProducto',
                'producto.unidad',
                'productoColorProveedor.color',
                'productoColorProveedor.proveedor'
            ]);

            return response()->json($listaPrecio, 201);
        } catch (\Exception $e) {
            Log::error('Error al crear precio: ' . $e->getMessage());
            return response()->json(['error' => 'Error al crear precio'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $listaPrecio = ListaPrecio::with([
                'producto.tipoProducto',
                'producto.unidad',
                'productoColorProveedor.color',
                'productoColorProveedor.proveedor'
            ])->findOrFail($id);

            return response()->json($listaPrecio);
        } catch (\Exception $e) {
            Log::error('Error al cargar precio: ' . $e->getMessage());
            return response()->json(['error' => 'Precio no encontrado'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $listaPrecio = ListaPrecio::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'producto_id' => 'sometimes|required|exists:productos,id',
                'producto_color_proveedor_id' => 'nullable|exists:producto_color_proveedor,id',
                'precio_costo' => 'sometimes|required|numeric|min:0',
                'margen' => 'sometimes|required|numeric|min:0|max:100',
                'vigencia_desde' => 'nullable|date',
                'vigencia_hasta' => 'nullable|date|after:vigencia_desde',
                'activo' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Actualizar campos
            if ($request->has('producto_id')) {
                $listaPrecio->producto_id = $request->producto_id;
            }

            if ($request->has('producto_color_proveedor_id')) {
                $listaPrecio->producto_color_proveedor_id = $request->producto_color_proveedor_id;
            }

            if ($request->has('precio_costo')) {
                $listaPrecio->precio_costo = floatval($request->precio_costo);
            }

            if ($request->has('margen')) {
                $listaPrecio->margen = floatval($request->margen);
            }

            // Recalcular precio venta
            $listaPrecio->precio_venta = $listaPrecio->precio_costo * (1 + $listaPrecio->margen / 100);

            if ($request->has('vigencia_desde')) {
                $listaPrecio->vigencia_desde = $request->vigencia_desde;
            }

            if ($request->has('vigencia_hasta')) {
                $listaPrecio->vigencia_hasta = $request->vigencia_hasta;
            }

            if ($request->has('activo')) {
                $listaPrecio->activo = $request->activo;
            }

            $listaPrecio->save();

            $listaPrecio->load([
                'producto.tipoProducto',
                'producto.unidad',
                'productoColorProveedor.color',
                'productoColorProveedor.proveedor'
            ]);

            return response()->json($listaPrecio);
        } catch (\Exception $e) {
            Log::error('Error al actualizar precio: ' . $e->getMessage());
            return response()->json(['error' => 'Error al actualizar precio'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $listaPrecio = ListaPrecio::findOrFail($id);
            $listaPrecio->delete();

            return response()->json(['message' => 'Precio eliminado correctamente']);
        } catch (\Exception $e) {
            Log::error('Error al eliminar precio: ' . $e->getMessage());
            return response()->json(['error' => 'Error al eliminar precio'], 500);
        }
    }

    /**
     * Importar precios desde producto_color_proveedor
     */
    public function importarDesdeProductoColorProveedor(Request $request)
    {
        try {
            $productosColorProveedor = ProductoColorProveedor::with(['producto', 'color', 'proveedor'])
                ->whereNotNull('costo')
                ->where('costo', '>', 0)
                ->get();

            $creados = 0;
            $actualizados = 0;
            $margenDefault = $request->margen ?? 45;

            foreach ($productosColorProveedor as $pcp) {
                $precioCosto = floatval($pcp->costo);
                $precioVenta = $precioCosto * (1 + $margenDefault / 100);

                $listaPrecio = ListaPrecio::updateOrCreate(
                    [
                        'producto_id' => $pcp->producto_id,
                        'producto_color_proveedor_id' => $pcp->id
                    ],
                    [
                        'precio_costo' => $precioCosto,
                        'margen' => $margenDefault,
                        'precio_venta' => $precioVenta,
                        'vigencia_desde' => now(),
                        'vigencia_hasta' => now()->addYear(),
                        'activo' => true
                    ]
                );

                if ($listaPrecio->wasRecentlyCreated) {
                    $creados++;
                } else {
                    $actualizados++;
                }
            }

            return response()->json([
                'message' => 'Importación completada',
                'creados' => $creados,
                'actualizados' => $actualizados,
                'total' => $creados + $actualizados
            ]);
        } catch (\Exception $e) {
            Log::error('Error al importar precios: ' . $e->getMessage());
            return response()->json(['error' => 'Error al importar precios'], 500);
        }
    }

    /**
     * Exportar precios a Excel
     */
    public function exportar()
    {
        try {
            $listaPrecios = ListaPrecio::with([
                'producto',
                'productoColorProveedor.color',
                'productoColorProveedor.proveedor'
            ])->get();

            $data = $listaPrecios->map(function($lp) {
                return [
                    'ID' => $lp->id,
                    'Producto' => $lp->producto->nombre ?? '',
                    'Color' => $lp->productoColorProveedor->color->nombre ?? 'Sin color',
                    'Proveedor' => $lp->productoColorProveedor->proveedor->nombre ?? 'Sin proveedor',
                    'Precio Costo' => $lp->precio_costo,
                    'Margen %' => $lp->margen,
                    'Precio Venta' => $lp->precio_venta,
                    'Activo' => $lp->activo ? 'Sí' : 'No',
                    'Vigencia Desde' => $lp->vigencia_desde,
                    'Vigencia Hasta' => $lp->vigencia_hasta
                ];
            });

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error al exportar precios: ' . $e->getMessage());
            return response()->json(['error' => 'Error al exportar precios'], 500);
        }
    }
}
