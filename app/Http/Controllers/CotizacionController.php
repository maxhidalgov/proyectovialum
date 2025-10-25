<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;
use App\Models\Ventana;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use App\Models\EstadoCotizacion;
use Illuminate\Support\Facades\Storage;
use App\Models\Cliente;
use App\Services\BsaleClientService;

class CotizacionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
{
    $cotizaciones = Cotizacion::with(['cliente', 'vendedor', 'ventanas.tipoVentana', 'estado'])
        ->latest()
        ->get();

    return response()->json($cotizaciones);
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */

public function store(Request $request)
{
    Log::info('📩 COTIZACION RECIBIDA', $request->all());
    
    // ✅ AGREGAR ESTE LOG ESPECÍFICO PARA VER LAS VENTANAS
    Log::info('🔍 VENTANAS RECIBIDAS:', $request->ventanas ?? 'NO HAY VENTANAS');
    
    try {
        DB::beginTransaction();

        // Buscar o crear cliente localmente desde Bsale
        $cliente = \App\Models\Cliente::find($request->cliente_id);
        
        if (!$cliente) {
            Log::warning("⚠️ Cliente ID {$request->cliente_id} no existe localmente, buscando en Bsale...");
            
            // Intentar obtener el cliente desde Bsale
            try {
                $bsaleService = new BsaleClientService();
                $clienteBsale = $bsaleService->getClient($request->cliente_id);
                
                if ($clienteBsale) {
                    // Crear cliente localmente
                    $cliente = \App\Models\Cliente::create([
                        'id' => $clienteBsale['id'],
                        'razon_social' => $clienteBsale['razon_social'] ?? $clienteBsale['company'] ?? 'Cliente sin nombre',
                        'identification' => $clienteBsale['identification'] ?? '',
                        'email' => $clienteBsale['email'] ?? '',
                        'phone' => $clienteBsale['phone'] ?? '',
                        'address' => $clienteBsale['address'] ?? '',
                        'ciudad' => $clienteBsale['city'] ?? '',
                    ]);
                    
                    Log::info("✅ Cliente sincronizado desde Bsale: {$cliente->id}");
                } else {
                    throw new \Exception("Cliente no encontrado en Bsale");
                }
            } catch (\Exception $e) {
                Log::error("❌ Error al sincronizar cliente desde Bsale", [
                    'cliente_id' => $request->cliente_id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente seleccionado no existe en Bsale. Por favor, verifica que el cliente esté activo.',
                    'error' => $e->getMessage(),
                    'cliente_id' => $request->cliente_id
                ], 400);
            }
        }

        // Calcular total correctamente
        $totalVentanas = 0;
        if ($request->has('ventanas') && is_array($request->ventanas)) {
            $totalVentanas = collect($request->ventanas)->sum(function($v) {
                return ($v['precio'] ?? 0) * ($v['cantidad'] ?? 1);
            });
        }
        
        $totalProductos = 0;
        if ($request->has('productos') && is_array($request->productos)) {
            $totalProductos = collect($request->productos)->sum('total');
        }

        // Crear cotización con cliente validado
        $cotizacion = Cotizacion::create([
            'cliente_id' => $request->cliente_id,
            'vendedor_id' => $request->vendedor_id,
            'fecha' => $request->fecha,
            'estado_cotizacion_id' => $request->estado_cotizacion_id,
            'observaciones' => $request->observaciones,
            'total' => $totalVentanas + $totalProductos,
        ]);

        // Guardar ventanas y mantener referencia
        $ventanasGuardadas = [];

        if ($request->has('ventanas') && is_array($request->ventanas)) {
            foreach ($request->ventanas as $index => $ventana) {
            Log::info("🔍 VENTANA $index:", $ventana);
            
            $ventanasGuardadas[] = $cotizacion->ventanas()->create([
                'tipo_ventana_id' => $ventana['tipo_ventana_id'] ?? null,           // ✅ CORREGIDO
                'ancho' => $ventana['ancho'] ?? null,
                'alto' => $ventana['alto'] ?? null,
                'cantidad' => $ventana['cantidad'] ?? 1,
                'color_id' => $ventana['color_id'] ?? null,                        // ✅ CORREGIDO
                'producto_vidrio_proveedor_id' => $ventana['producto_vidrio_proveedor_id'] ?? null, // ✅ CORREGIDO
                'costo' => $ventana['costo'] ?? 0,
                'precio' => $ventana['precio'] ?? 0,
                // Agregar campos para Bay Window y correderas
                'hojas_totales' => $ventana['hojas_totales'] ?? null,
                'hojas_moviles' => $ventana['hojas_moviles'] ?? null,
                'hoja_movil_seleccionada' => $ventana['hoja_movil_seleccionada'] ?? null,
                'hoja1_al_frente' => $ventana['hoja1_al_frente'] ?? null,
                'ancho_izquierda' => $ventana['ancho_izquierda'] ?? null,
                'ancho_centro' => $ventana['ancho_centro'] ?? null,
                'ancho_derecha' => $ventana['ancho_derecha'] ?? null,
                'tipo_ventana_izquierda' => isset($ventana['tipo_ventana_izquierda']) ? json_encode($ventana['tipo_ventana_izquierda']) : null,
                'tipo_ventana_centro' => isset($ventana['tipo_ventana_centro']) ? json_encode($ventana['tipo_ventana_centro']) : null,
                'tipo_ventana_derecha' => isset($ventana['tipo_ventana_derecha']) ? json_encode($ventana['tipo_ventana_derecha']) : null,
            ]);
        }
        } // Cierre del if de ventanas

        // Guardar productos si existen
        if ($request->has('productos') && is_array($request->productos)) {
            Log::info("💼 PROCESANDO " . count($request->productos) . " PRODUCTOS");
            
            foreach ($request->productos as $index => $producto) {
                Log::info("🔍 PRODUCTO $index:", $producto);
                
                \App\Models\CotizacionDetalle::create([
                    'cotizacion_id' => $cotizacion->id,
                    'tipo_item' => 'producto',
                    'producto_lista_id' => $producto['producto_lista_id'] ?? null,
                    'lista_precio_id' => $producto['lista_precio_id'] ?? null,
                    'descripcion' => $producto['descripcion'] ?? '',
                    'cantidad' => $producto['cantidad'] ?? 1,
                    'precio_unitario' => $producto['precio_unitario'] ?? 0,
                    'total' => $producto['total'] ?? 0,
                    // Campos adicionales para vidrios
                    'esVidrio' => $producto['esVidrio'] ?? false,
                    'ancho_mm' => $producto['ancho_mm'] ?? null,
                    'alto_mm' => $producto['alto_mm'] ?? null,
                    'm2' => $producto['m2'] ?? null,
                    'pulido' => $producto['pulido'] ?? false
                ]);
            }
        }

        // Guardar imágenes con mejor manejo de errores
            if ($request->has('imagenes_ventanas') && is_array($request->imagenes_ventanas)) {
                Log::info("🖼️ PROCESANDO " . count($request->imagenes_ventanas) . " IMÁGENES");
                
                foreach ($request->imagenes_ventanas as $index => $base64) {
                    try {
                        if (!$base64 || $base64 === null) {
                            Log::info("⚠️ Imagen vacía en índice $index, saltando...");
                            continue;
                        }

                        Log::info("🖼️ PROCESANDO IMAGEN $index - Tamaño: " . strlen($base64) . " caracteres");

                        // Verificar si tiene el prefijo correcto
                        if (!str_starts_with($base64, 'data:image/png;base64,')) {
                            Log::warning("⚠️ Imagen en índice $index no tiene formato correcto, prefijo: " . substr($base64, 0, 30));
                            continue;
                        }

                        $image = str_replace('data:image/png;base64,', '', $base64);
                        $image = str_replace(' ', '+', $image);
                        $imageData = base64_decode($image);

                        if ($imageData === false) {
                            Log::error("❌ Error al decodificar imagen en índice $index");
                            continue;
                        }

                        Log::info("✅ Imagen decodificada correctamente - Tamaño: " . strlen($imageData) . " bytes");

                        $imageName = 'cotizacion_' . $cotizacion->id . '_ventana_' . $index . '_' . time() . '.png';

                        // Intentar guardar localmente primero como backup
                        $localPath = 'imagenes_ventanas/' . $imageName;
                        Storage::disk('public')->put($localPath, $imageData);
                        Log::info("✅ Imagen guardada localmente: " . storage_path('app/public/' . $localPath));

                        // Intentar subir por FTP si está configurado
                        try {
                            // Verificar si la configuración FTP existe
                            $ftpConfig = config('filesystems.disks.ftp_cpanel');
                            if ($ftpConfig) {
                                Log::info("🌐 Intentando subir por FTP con config: " . json_encode(array_keys($ftpConfig)));
                                
                                Storage::disk('ftp_cpanel')->put($imageName, $imageData);
                                Log::info("✅ Imagen subida por FTP: $imageName");
                                
                                // Verificar que se subió correctamente
                                if (Storage::disk('ftp_cpanel')->exists($imageName)) {
                                    Log::info("✅ Confirmado: Imagen existe en FTP");
                                } else {
                                    Log::warning("⚠️ La imagen no se encuentra en FTP después de subirla");
                                }
                            } else {
                                Log::warning("⚠️ Configuración FTP no encontrada");
                            }
                        } catch (\Exception $ftpError) {
                            Log::error("❌ Error FTP: " . $ftpError->getMessage());
                            Log::error("❌ FTP Stack trace: " . $ftpError->getTraceAsString());
                            // Continuar con almacenamiento local
                        }

                        // Asociar imagen a ventana
                        if (isset($ventanasGuardadas[$index])) {
                            $ventanasGuardadas[$index]->update([
                                'imagen' => $imageName
                            ]);
                            Log::info("✅ Imagen asociada a ventana ID {$ventanasGuardadas[$index]->id}");
                        } else {
                            Log::warning("⚠️ No se encontró ventana para imagen en índice $index");
                        }

                    } catch (\Exception $imageError) {
                        Log::error("❌ Error procesando imagen $index: " . $imageError->getMessage());
                        Log::error("❌ Stack trace: " . $imageError->getTraceAsString());
                        continue;
                    }
                }
            } else {
                Log::info("ℹ️ No se recibieron imágenes o no es un array válido");
                Log::info("ℹ️ Datos recibidos: " . json_encode($request->get('imagenes_ventanas')));
            }

        DB::commit();
        return response()->json(['message' => 'Cotización guardada correctamente'], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('❌ Error al guardar cotización: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Error al guardar cotización', 'detalle' => $e->getMessage()], 500);
    }
}



    /**
     * Display the specified resource.
     */
    public function show($id)
    {
    $cotizacion = Cotizacion::with([
        'cliente',
        'clienteFacturacion', // ✅ Agregada relación clienteFacturacion
        'vendedor',
        'ventanas.tipoVentana',
        'ventanas',
        'ventanas.color',
        'ventanas.productoVidrioProveedor.producto',
        'ventanas.productoVidrioProveedor.proveedor',
        'estado',
        'detalles.productoLista.tipoProducto',
        'detalles.productoLista.unidad',
        'detalles.listaPrecio'
    ])->findOrFail($id);


    // Calcular precio_total por ventana
    $cotizacion->ventanas->transform(function ($ventana) {
        $ventana->precio_total = ($ventana->precio ?? 0) * ($ventana->cantidad ?? 1);
        return $ventana;
    });

    // Calcular total de la cotización (ventanas + productos)
    $totalVentanas = $cotizacion->ventanas->sum('precio_total');
    $totalProductos = $cotizacion->detalles->where('tipo_item', 'producto')->sum('total');
    $cotizacion->total_general = $totalVentanas + $totalProductos;
    
    return response()->json($cotizacion);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cotizacion $cotizacion)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $cotizacion = Cotizacion::with(['ventanas', 'detalles'])->findOrFail($id);

        DB::transaction(function () use ($request, $cotizacion) {
            $cotizacion->update([
                'cliente_id' => $request->cliente_id,
                'fecha' => $request->fecha,
                'estado_cotizacion_id' => $request->estado_cotizacion_id,
                'observaciones' => $request->observaciones,
            ]);

            // ========== MANEJAR VENTANAS ==========
            $idsRecibidos = collect($request->ventanas)->pluck('id')->filter()->all();

            // Eliminar ventanas que ya no existen en el request
            $cotizacion->ventanas()
                ->whereNotIn('id', $idsRecibidos)
                ->delete();

            foreach ($request->ventanas as $ventanaData) {
                if (isset($ventanaData['id'])) {
                    // Actualizar ventana existente
                    $ventana = $cotizacion->ventanas()->where('id', $ventanaData['id'])->first();
                    if ($ventana) {
                        $ventana->update([
                            'tipo_ventana_id' => $ventanaData['tipo_ventana_id'],
                            'ancho' => $ventanaData['ancho'],
                            'alto' => $ventanaData['alto'],
                            'color_id' => $ventanaData['color_id'],
                            'producto_vidrio_proveedor_id' => $ventanaData['producto_vidrio_proveedor_id'],
                            'costo' => $ventanaData['costo'],
                            'precio' => $ventanaData['precio'],
                            'costo_unitario' => $ventanaData['costo_unitario'] ?? null,
                            'precio_unitario' => $ventanaData['precio_unitario'] ?? null,
                            'hojas_totales' => $ventanaData['tipo_ventana_id'] === 3 ? $ventanaData['hojas_totales'] : null,
                            'hojas_moviles' => $ventanaData['tipo_ventana_id'] === 3 ? $ventanaData['hojas_moviles'] : null,
                            'cantidad' => $ventanaData['cantidad'] ?? 1,
                        ]);
                    }
                } else {
                    // Crear nueva ventana
                    $cotizacion->ventanas()->create([
                        'tipo_ventana_id' => $ventanaData['tipo_ventana_id'],
                        'ancho' => $ventanaData['ancho'],
                        'alto' => $ventanaData['alto'],
                        'color_id' => $ventanaData['color_id'],
                        'producto_vidrio_proveedor_id' => $ventanaData['producto_vidrio_proveedor_id'],
                        'costo' => $ventanaData['costo'] ?? 0,
                        'precio' => $ventanaData['precio'] ?? 0,
                        'hojas_totales' => $ventanaData['tipo_ventana_id'] === 3 ? $ventanaData['hojas_totales'] : null,
                        'hojas_moviles' => $ventanaData['tipo_ventana_id'] === 3 ? $ventanaData['hojas_moviles'] : null,
                        'cantidad' => $ventanaData['cantidad'] ?? 1,
                    ]);
                }
            }

            // ========== MANEJAR PRODUCTOS ==========
            if ($request->has('productos')) {
                $idsProductosRecibidos = collect($request->productos)->pluck('id')->filter()->all();

                // Eliminar productos que ya no existen en el request
                $cotizacion->detalles()
                    ->where('tipo_item', 'producto')
                    ->whereNotIn('id', $idsProductosRecibidos)
                    ->delete();

                foreach ($request->productos as $productoData) {
                    if (isset($productoData['id'])) {
                        // Actualizar producto existente
                        $detalle = $cotizacion->detalles()
                            ->where('id', $productoData['id'])
                            ->where('tipo_item', 'producto')
                            ->first();
                        
                        if ($detalle) {
                            $detalle->update([
                                'producto_lista_id' => $productoData['producto_lista_id'],
                                'lista_precio_id' => $productoData['lista_precio_id'],
                                'descripcion' => $productoData['descripcion'],
                                'cantidad' => $productoData['cantidad'],
                                'precio_unitario' => $productoData['precio_unitario'],
                                'total' => $productoData['total'],
                            ]);
                        }
                    } else {
                        // Crear nuevo producto
                        $cotizacion->detalles()->create([
                            'tipo_item' => 'producto',
                            'producto_lista_id' => $productoData['producto_lista_id'],
                            'lista_precio_id' => $productoData['lista_precio_id'],
                            'descripcion' => $productoData['descripcion'],
                            'cantidad' => $productoData['cantidad'],
                            'precio_unitario' => $productoData['precio_unitario'],
                            'total' => $productoData['total'],
                        ]);
                    }
                }
            }

            // ========== MANEJAR IMÁGENES DE VENTANAS (EDICIÓN) ==========
            if ($request->has('imagenes_ventanas') && is_array($request->imagenes_ventanas)) {
                Log::info("🖼️ ACTUALIZANDO " . count($request->imagenes_ventanas) . " IMÁGENES");
                
                // Obtener ventanas actualizadas
                $ventanasActualizadas = $cotizacion->ventanas()->orderBy('id')->get();
                
                foreach ($request->imagenes_ventanas as $index => $base64) {
                    try {
                        if (!$base64 || $base64 === null) {
                            Log::info("⚠️ Imagen vacía en índice $index, saltando...");
                            continue;
                        }

                        if (!str_starts_with($base64, 'data:image/png;base64,')) {
                            Log::warning("⚠️ Imagen en índice $index no tiene formato correcto");
                            continue;
                        }

                        $image = str_replace('data:image/png;base64,', '', $base64);
                        $image = str_replace(' ', '+', $image);
                        $imageData = base64_decode($image);

                        if ($imageData === false) {
                            Log::error("❌ Error decodificando base64 para imagen $index");
                            continue;
                        }

                        $imageName = 'cotizacion_' . $cotizacion->id . '_ventana_' . $index . '_' . time() . '.png';
                        $localPath = 'imagenes_ventanas/' . $imageName;

                        // Guardar localmente
                        Storage::disk('public')->put($localPath, $imageData);
                        Log::info("✅ Imagen guardada localmente: $localPath");

                        // Intentar subir por FTP si está configurado
                        try {
                            $ftpConfig = config('filesystems.disks.ftp_cpanel');
                            if ($ftpConfig) {
                                Storage::disk('ftp_cpanel')->put($imageName, $imageData);
                                Log::info("✅ Imagen subida por FTP: $imageName");
                            }
                        } catch (\Exception $ftpError) {
                            Log::error("❌ Error FTP: " . $ftpError->getMessage());
                        }

                        // Actualizar campo imagen en la ventana correspondiente
                        if (isset($ventanasActualizadas[$index])) {
                            $ventanasActualizadas[$index]->update([
                                'imagen' => $imageName
                            ]);
                            Log::info("✅ Imagen asociada a ventana ID {$ventanasActualizadas[$index]->id}");
                        } else {
                            Log::warning("⚠️ No se encontró ventana para imagen en índice $index");
                        }

                    } catch (\Exception $e) {
                        Log::error("❌ Error procesando imagen $index: " . $e->getMessage());
                    }
                }
            }
        });

        return response()->json(['message' => 'Cotización actualizada correctamente']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cotizacion $cotizacion)
    {
        //
    }

    public function generarPDF($id)
    {
        $cotizacion = Cotizacion::with([
            'cliente',
            'vendedor',
            'estado',
            'ventanas.tipoVentana',
            'ventanas.color',
            'ventanas.productoVidrioProveedor.producto',
            'ventanas.productoVidrioProveedor.proveedor',
            'detalles.producto',
            'detalles.productoLista',
            'detalles.listaPrecio.producto',
            'detalles.listaPrecio.productoColorProveedor.color',
            'detalles.listaPrecio.productoColorProveedor.proveedor'
        ])->findOrFail($id);


    $pdf = Pdf::loadView('cotizaciones.pdf', compact('cotizacion'));

    return $pdf->download('cotizacion_' . $cotizacion->id . '.pdf');
    }

    public function duplicar($id)
    {
        $original = Cotizacion::with('ventanas', 'estado')->findOrFail($id);

        // Validar que solo se pueda duplicar si está en estado "Evaluación"
        if ($original->estado->nombre !== 'Evaluación') {
            return response()->json([
                'message' => 'Solo se pueden duplicar cotizaciones en estado Evaluación.'
            ], 403);
        }

        $nuevoEstadoId = EstadoCotizacion::where('nombre', 'Evaluación')->firstOrFail()->id;

        $nueva = Cotizacion::create([
            'cliente_id' => $original->cliente_id,
            'vendedor_id' => $original->vendedor_id,
            'fecha' => now()->toDateString(),
            'estado_cotizacion_id' => $nuevoEstadoId,
            'observaciones' => $original->observaciones,
            'total' => $original->total,
            'origen_id' => $original->id,
        ]);

        foreach ($original->ventanas as $ventana) {
            $nueva->ventanas()->create([
                'tipo_ventana_id' => $ventana->tipo_ventana_id,
                'ancho' => $ventana->ancho,
                'alto' => $ventana->alto,
                'cantidad' => $ventana->cantidad ?? 1,
                'color_id' => $ventana->color_id,
                'producto_vidrio_proveedor_id' => $ventana->producto_vidrio_proveedor_id,
                'costo' => $ventana->costo ?? 0,
                'precio' => $ventana->precio ?? 0,
            ]);
        }

        return response()->json(['id' => $nueva->id, 'message' => 'Cotización duplicada']);
    }

public function getAprobadas()
{
    try {
        Log::info("📄 Obteniendo cotizaciones aprobadas");

        $cotizaciones = Cotizacion::with([
            'cliente',
            'clienteFacturacion', // ✅ Agregada relación clienteFacturacion
            'vendedor',
            'ventanas' => function($query) {
                $query->with([
                    'tipoVentana', // ✅ Usar camelCase
                    'color',
                    'productoVidrioProveedor' => function($q) {
                        $q->with(['producto', 'proveedor']);
                    }
                ]);
            },
            'estado'
        ])
        ->whereHas('estado', function($query) {
            $query->whereIn('nombre', ['Aprobada', 'Facturada', 'Pagada']);
        })
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($cotizacion) {
            // Mapear estado para el frontend (simplificado)
            $cotizacion->estado_facturacion = match($cotizacion->estado->nombre ?? '') {
                'Aprobada' => 'aprobada',
                'Facturada' => 'facturada', 
                'Pagada' => 'pagada',
                default => 'aprobada'
            };
            
            // Mapear cliente para compatibilidad
            if ($cotizacion->cliente) {
                $cotizacion->cliente->nombre = trim($cotizacion->cliente->first_name . ' ' . $cotizacion->cliente->last_name);
                $cotizacion->cliente->email = $cotizacion->cliente->email;
                $cotizacion->cliente->telefono = $cotizacion->cliente->phone;
                $cotizacion->cliente->direccion = $cotizacion->cliente->address;
            }
            
            // Agregar número de cotización
            $cotizacion->numero = $cotizacion->id;
            
            // Fecha de aprobación
            $cotizacion->fecha_aprobacion = $cotizacion->updated_at;
            
            // ✅ Procesar ventanas y mapear tipo_ventana para compatibilidad
            $cotizacion->ventanas = $cotizacion->ventanas->map(function($ventana) {
                $ventana->precio_unitario = $ventana->precio;
                
                // ✅ Mapear tipoVentana a tipo_ventana para compatibilidad con el frontend
                if ($ventana->tipoVentana) {
                    $ventana->tipo_ventana = $ventana->tipoVentana;
                }
                
                return $ventana;
            });
            
            return $cotizacion;
        });

        Log::info("✅ Cotizaciones obtenidas", [
            'count' => $cotizaciones->count(),
            'estados' => $cotizaciones->groupBy('estado_facturacion')->map->count()
        ]);

        return response()->json([
            'success' => true,
            'cotizaciones' => $cotizaciones
        ]);

    } catch (\Exception $e) {
        Log::error("❌ Error obteniendo cotizaciones aprobadas:", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Error interno del servidor',
            'message' => $e->getMessage()
        ], 500);
    }
}

}
