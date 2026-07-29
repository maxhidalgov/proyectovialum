<?php

namespace App\Http\Controllers;

use App\Models\ListaPrecio;
use App\Models\Producto;
use App\Models\ProductoColorProveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'color',
                'proveedorSugerido',
                // Mantener por compatibilidad
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

            if ($request->has('search') && $request->search !== '') {
                $palabras = array_filter(explode(' ', trim($request->search)));
                $query->whereHas('producto', function($q) use ($palabras) {
                    foreach ($palabras as $palabra) {
                        $q->where('nombre', 'LIKE', "%{$palabra}%");
                    }
                });
            }

            $listaPrecios = $query->orderBy('created_at', 'desc')->get();

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
                'color_id' => 'required|exists:colores,id',
                'margen' => 'required|numeric|min:0|max:100',
                'vigencia_desde' => 'nullable|date',
                'vigencia_hasta' => 'nullable|date|after:vigencia_desde',
                'activo' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Calcular costo máximo automáticamente
            $costoData = ListaPrecio::calcularCostoMaximo($request->producto_id, $request->color_id);
            
            if ($costoData['costo_maximo'] == 0) {
                return response()->json([
                    'error' => 'No hay proveedores configurados para este producto y color'
                ], 422);
            }

            $precioCosto = $costoData['costo_maximo'];
            $margen = floatval($request->margen);

            if ($margen >= 100) {
                return response()->json(['error' => 'El margen no puede ser 100% o mayor'], 422);
            }

            // Si el frontend envió precio_venta directo (calculado desde precio bruto),
            // usarlo exactamente para evitar pérdida de precisión al round-trip por margen.
            if ($request->filled('precio_venta') && floatval($request->precio_venta) > 0) {
                $precioVenta = floatval($request->precio_venta);
                $margen = $precioCosto > 0 ? (1 - $precioCosto / $precioVenta) * 100 : $margen;
            } else {
                $precioVenta = $precioCosto / (1 - $margen / 100);
            }

            $listaPrecio = ListaPrecio::create([
                'producto_id' => $request->producto_id,
                'color_id' => $request->color_id,
                'proveedor_sugerido_id' => $costoData['proveedor_id'],
                'producto_color_proveedor_id' => $costoData['producto_color_proveedor_id'], // Mantener compatibilidad
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
                'color',
                'proveedorSugerido'
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
                'color_id' => 'sometimes|required|exists:colores,id',
                'margen' => 'sometimes|required|numeric|min:0|max:100',
                'vigencia_desde' => 'nullable|date',
                'vigencia_hasta' => 'nullable|date|after:vigencia_desde',
                'activo' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Si cambia producto o color, recalcular costo
            $recalcularCosto = false;
            
            if ($request->has('producto_id') && $request->producto_id != $listaPrecio->producto_id) {
                $listaPrecio->producto_id = $request->producto_id;
                $recalcularCosto = true;
            }

            if ($request->has('color_id') && $request->color_id != $listaPrecio->color_id) {
                $listaPrecio->color_id = $request->color_id;
                $recalcularCosto = true;
            }

            if ($recalcularCosto) {
                $costoData = ListaPrecio::calcularCostoMaximo($listaPrecio->producto_id, $listaPrecio->color_id);
                
                if ($costoData['costo_maximo'] == 0) {
                    return response()->json([
                        'error' => 'No hay proveedores configurados para este producto y color'
                    ], 422);
                }
                
                $listaPrecio->precio_costo = $costoData['costo_maximo'];
                $listaPrecio->proveedor_sugerido_id = $costoData['proveedor_id'];
                $listaPrecio->producto_color_proveedor_id = $costoData['producto_color_proveedor_id'];
            }

            if ($request->has('margen')) {
                $listaPrecio->margen = floatval($request->margen);
            }

            if ($listaPrecio->margen >= 100) {
                return response()->json(['error' => 'El margen no puede ser 100% o mayor'], 422);
            }

            // Si el frontend envió precio_venta directo (desde precio bruto), usarlo exactamente.
            if ($request->filled('precio_venta') && floatval($request->precio_venta) > 0) {
                $listaPrecio->precio_venta = floatval($request->precio_venta);
                if ($listaPrecio->precio_costo > 0) {
                    $listaPrecio->margen = (1 - $listaPrecio->precio_costo / $listaPrecio->precio_venta) * 100;
                }
            } else {
                $listaPrecio->precio_venta = $listaPrecio->precio_costo / (1 - $listaPrecio->margen / 100);
            }

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
                'color',
                'proveedorSugerido'
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
                $precioVenta = $margenDefault < 100 ? $precioCosto / (1 - $margenDefault / 100) : 0;

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

    // ── Ajuste masivo de costos por proveedor ───────────────────────────────

    /** Combinaciones producto+color+costo afectadas por el ajuste. */
    private function pcpsAfectados(array $data)
    {
        return DB::table('producto_color_proveedor as pcp')
            ->join('productos as p', 'p.id', '=', 'pcp.producto_id')
            ->leftJoin('colores as c', 'c.id', '=', 'pcp.color_id')
            ->where('pcp.proveedor_id', $data['proveedor_id'])
            ->where('pcp.costo', '>', 0)
            ->when(!empty($data['tipos']), fn ($w) => $w->whereIn('p.tipo_producto_id', $data['tipos']))
            ->orderBy('p.nombre')
            ->get(['pcp.id', 'pcp.producto_id', 'pcp.color_id', 'pcp.costo', 'p.nombre as producto', 'c.nombre as color']);
    }

    /** POST /api/lista-precios/ajuste-masivo/preview */
    public function ajusteMasivoPreview(Request $request)
    {
        $data = $request->validate([
            'proveedor_id' => 'required|integer',
            'porcentaje'   => 'required|numeric',
            'tipos'        => 'nullable|array',
        ]);

        $factor = 1 + $data['porcentaje'] / 100;
        $rows = $this->pcpsAfectados($data);

        $items = $rows->map(fn ($r) => [
            'producto'     => trim($r->producto . ($r->color ? " - {$r->color}" : '')),
            'costo_actual' => (float) $r->costo,
            'costo_nuevo'  => round($r->costo * $factor, 2),
        ]);

        return response()->json(['total' => $items->count(), 'items' => $items->take(300)->values()]);
    }

    /** POST /api/lista-precios/ajuste-masivo/aplicar */
    public function ajusteMasivoAplicar(Request $request)
    {
        $data = $request->validate([
            'proveedor_id'     => 'required|integer',
            'porcentaje'       => 'required|numeric',
            'tipos'            => 'nullable|array',
            'actualizar_venta' => 'nullable|boolean',
        ]);

        $factor = 1 + $data['porcentaje'] / 100;
        $actualizarVenta = (bool) ($data['actualizar_venta'] ?? true);
        $rows = $this->pcpsAfectados($data);

        $costos = 0;
        $precios = 0;

        DB::transaction(function () use ($rows, $factor, $actualizarVenta, &$costos, &$precios) {
            // 1) Actualizar el costo de cada combinación del proveedor
            foreach ($rows as $r) {
                DB::table('producto_color_proveedor')->where('id', $r->id)
                    ->update(['costo' => round($r->costo * $factor, 2), 'updated_at' => now()]);
                $costos++;
            }

            // 2) Recalcular precio_costo (máximo entre proveedores) y precio_venta (manteniendo margen)
            foreach ($rows as $r) {
                $lps = ListaPrecio::where('producto_id', $r->producto_id)
                    ->where(function ($q) use ($r) {
                        $q->where('color_id', $r->color_id)
                          ->orWhere('producto_color_proveedor_id', $r->id);
                    })->get();

                foreach ($lps as $lp) {
                    $cm = ListaPrecio::calcularCostoMaximo($lp->producto_id, $r->color_id);
                    if ($cm['costo_maximo'] <= 0) continue;

                    $lp->precio_costo = $cm['costo_maximo'];
                    $lp->proveedor_sugerido_id = $cm['proveedor_id'];
                    $lp->producto_color_proveedor_id = $cm['producto_color_proveedor_id'];
                    if ($actualizarVenta && (float) $lp->margen < 100) {
                        $lp->precio_venta = round($lp->precio_costo / (1 - $lp->margen / 100));
                    }
                    $lp->save();
                    $precios++;
                }
            }
        });

        return response()->json([
            'ok' => true,
            'costos_actualizados'  => $costos,
            'precios_actualizados' => $precios,
        ]);
    }
}
