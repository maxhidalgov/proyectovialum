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
                    DB::rollBack();
                    throw new \Exception("Cliente no encontrado en Bsale");
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("❌ Error al sincronizar cliente desde Bsale", [
                    'cliente_id' => $request->cliente_id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente seleccionado no existe. Por favor, verifica que el cliente esté registrado en el sistema.',
                    'error' => $e->getMessage(),
                    'cliente_id' => $request->cliente_id
                ], 400);
            }
        }

        // Validación adicional: asegurarse que el cliente exista antes de continuar
        if (!$cliente || !$cliente->id) {
            DB::rollBack();
            Log::error("❌ Cliente no pudo ser validado", ['cliente_id' => $request->cliente_id]);
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: el cliente no existe en el sistema.',
                'cliente_id' => $request->cliente_id
            ], 400);
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

        // Crear cotización con cliente validado - USAR $cliente->id que puede ser diferente al request
        $cotizacion = Cotizacion::create([
            'cliente_id' => $cliente->id, // ✅ Usar el ID del cliente sincronizado/encontrado
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
            $ventanasGuardadas[] = $cotizacion->ventanas()->create([
                'tipo_ventana_id' => $ventana['tipo_ventana_id'] ?? null,
                'ancho' => $ventana['ancho'] ?? null,
                'alto' => $ventana['alto'] ?? null,
                'cantidad' => $ventana['cantidad'] ?? 1,
                'color_id' => $ventana['color_id'] ?? null,
                'producto_vidrio_proveedor_id' => $ventana['producto_vidrio_proveedor_id'] ?? null,
                'costo' => $ventana['costo'] ?? 0,
                'precio' => $ventana['precio'] ?? 0,
                'hojas_totales' => $ventana['hojas_totales'] ?? null,
                'hojas_moviles' => $ventana['hojas_moviles'] ?? null,
                'hoja_movil_seleccionada' => $ventana['hoja_movil_seleccionada'] ?? null,
                'hoja1_al_frente' => $ventana['hoja1_al_frente'] ?? null,
                'ancho_izquierda' => $ventana['ancho_izquierda'] ?? null,
                'ancho_centro' => $ventana['ancho_centro'] ?? null,
                'ancho_derecha' => $ventana['ancho_derecha'] ?? null,
                'tipo_ventana_izquierda' => $ventana['tipo_ventana_izquierda'] ?? null,
                'tipo_ventana_centro'    => $ventana['tipo_ventana_centro'] ?? null,
                'tipo_ventana_derecha'   => $ventana['tipo_ventana_derecha'] ?? null,
                // Parámetros extra para recalculo y hoja de cortes
                'config' => array_filter([
                    'tipo_vidrio'     => $ventana['tipo_vidrio'] ?? $ventana['tipoVidrio'] ?? null,
                    'manillon'        => $ventana['manillon'] ?? null,
                    'proveedor_vidrio'=> $ventana['proveedor_vidrio'] ?? $ventana['proveedorVidrio'] ?? null,
                ], fn($v) => $v !== null),
            ]);
        }
        } // Cierre del if de ventanas

        // Guardar productos si existen
        if ($request->has('productos') && is_array($request->productos)) {
            foreach ($request->productos as $producto) {
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
                foreach ($request->imagenes_ventanas as $index => $base64) {
                    try {
                        if (!$base64 || $base64 === null) {
                            continue;
                        }

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

                        $imageName = 'cotizacion_' . $cotizacion->id . '_ventana_' . $index . '_' . time() . '.png';
                        $localPath = 'imagenes_ventanas/' . $imageName;
                        Storage::disk('public')->put($localPath, $imageData);

                        try {
                            $ftpConfig = config('filesystems.disks.ftp_cpanel');
                            if ($ftpConfig) {
                                Storage::disk('ftp_cpanel')->put($imageName, $imageData);
                            }
                        } catch (\Exception $ftpError) {
                            Log::error("❌ Error FTP: " . $ftpError->getMessage());
                        }

                        if (isset($ventanasGuardadas[$index])) {
                            $ventanasGuardadas[$index]->update(['imagen' => $imageName]);
                        } else {
                            Log::warning("⚠️ No se encontró ventana para imagen en índice $index");
                        }

                    } catch (\Exception $imageError) {
                        Log::error("❌ Error procesando imagen $index: " . $imageError->getMessage());
                        continue;
                    }
                }
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

            // Collect ventana models in request order so image indices match exactly
            $ventanasEnOrden = [];

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
                            'hojas_totales' => $ventanaData['hojas_totales'] ?? null,
                            'hojas_moviles' => $ventanaData['hojas_moviles'] ?? null,
                            'hoja_movil_seleccionada' => $ventanaData['hoja_movil_seleccionada'] ?? null,
                            'hoja1_al_frente' => $ventanaData['hoja1_al_frente'] ?? null,
                            'cantidad' => $ventanaData['cantidad'] ?? 1,
                            // Bay Window
                            'ancho_izquierda' => $ventanaData['ancho_izquierda'] ?? null,
                            'ancho_centro'    => $ventanaData['ancho_centro'] ?? null,
                            'ancho_derecha'   => $ventanaData['ancho_derecha'] ?? null,
                            'tipo_ventana_izquierda' => $ventanaData['tipo_ventana_izquierda'] ?? null,
                            'tipo_ventana_centro'    => $ventanaData['tipo_ventana_centro'] ?? null,
                            'tipo_ventana_derecha'   => $ventanaData['tipo_ventana_derecha'] ?? null,
                            'config' => array_filter([
                                'tipo_vidrio'      => $ventanaData['tipo_vidrio'] ?? $ventanaData['tipoVidrio'] ?? null,
                                'manillon'         => $ventanaData['manillon'] ?? null,
                                'proveedor_vidrio' => $ventanaData['proveedor_vidrio'] ?? $ventanaData['proveedorVidrio'] ?? null,
                            ], fn($v) => $v !== null) ?: null,
                        ]);
                        $ventanasEnOrden[] = $ventana;
                    }
                } else {
                    // Crear nueva ventana
                    $ventana = $cotizacion->ventanas()->create([
                        'tipo_ventana_id' => $ventanaData['tipo_ventana_id'],
                        'ancho' => $ventanaData['ancho'],
                        'alto' => $ventanaData['alto'],
                        'color_id' => $ventanaData['color_id'],
                        'producto_vidrio_proveedor_id' => $ventanaData['producto_vidrio_proveedor_id'],
                        'costo' => $ventanaData['costo'] ?? 0,
                        'precio' => $ventanaData['precio'] ?? 0,
                        'hojas_totales' => $ventanaData['hojas_totales'] ?? null,
                        'hojas_moviles' => $ventanaData['hojas_moviles'] ?? null,
                        'hoja_movil_seleccionada' => $ventanaData['hoja_movil_seleccionada'] ?? null,
                        'hoja1_al_frente' => $ventanaData['hoja1_al_frente'] ?? null,
                        'cantidad' => $ventanaData['cantidad'] ?? 1,
                        // Bay Window
                        'ancho_izquierda' => $ventanaData['ancho_izquierda'] ?? null,
                        'ancho_centro'    => $ventanaData['ancho_centro'] ?? null,
                        'ancho_derecha'   => $ventanaData['ancho_derecha'] ?? null,
                        'tipo_ventana_izquierda' => $ventanaData['tipo_ventana_izquierda'] ?? null,
                        'tipo_ventana_centro'    => $ventanaData['tipo_ventana_centro'] ?? null,
                        'tipo_ventana_derecha'   => $ventanaData['tipo_ventana_derecha'] ?? null,
                        'config' => array_filter([
                            'tipo_vidrio'      => $ventanaData['tipo_vidrio'] ?? $ventanaData['tipoVidrio'] ?? null,
                            'manillon'         => $ventanaData['manillon'] ?? null,
                            'proveedor_vidrio' => $ventanaData['proveedor_vidrio'] ?? $ventanaData['proveedorVidrio'] ?? null,
                        ], fn($v) => $v !== null) ?: null,
                    ]);
                    $ventanasEnOrden[] = $ventana;
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
                foreach ($request->imagenes_ventanas as $index => $base64) {
                    try {
                        if (!$base64 || $base64 === null) {
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

                        Storage::disk('public')->put($localPath, $imageData);

                        try {
                            $ftpConfig = config('filesystems.disks.ftp_cpanel');
                            if ($ftpConfig) {
                                Storage::disk('ftp_cpanel')->put($imageName, $imageData);
                            }
                        } catch (\Exception $ftpError) {
                            Log::error("❌ Error FTP: " . $ftpError->getMessage());
                        }

                        if (isset($ventanasEnOrden[$index])) {
                            $ventanasEnOrden[$index]->update(['imagen' => $imageName]);
                        } else {
                            Log::warning("⚠️ No se encontró ventana para imagen en índice $index");
                        }

                    } catch (\Exception $e) {
                        Log::error("❌ Error procesando imagen $index: " . $e->getMessage());
                    }
                }
            }
        });

        // Recalcular y guardar el total actualizado
        $totalVentanas = collect($request->ventanas)->sum(fn($v) => ($v['precio'] ?? 0) * ($v['cantidad'] ?? 1));
        $totalProductos = $request->has('productos') ? collect($request->productos)->sum('total') : 0;
        $cotizacion->update(['total' => $totalVentanas + $totalProductos]);

        return response()->json(['message' => 'Cotización actualizada correctamente']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cotizacion $cotizacion)
    {
        $cotizacion->load('estado');

        if ($cotizacion->estado->nombre !== 'Evaluación') {
            return response()->json([
                'message' => 'Solo se pueden eliminar cotizaciones en estado Evaluación.'
            ], 403);
        }

        DB::transaction(function () use ($cotizacion) {
            $cotizacion->ventanas()->delete();
            $cotizacion->detalles()->delete();
            $cotizacion->delete();
        });

        return response()->json(['message' => 'Cotización eliminada correctamente']);
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

        // Pre-fetch ventana images as base64 so DomPDF doesn't make
        // external HTTP requests during rendering (fails on Railway).
        // Locally images are in storage/app/public/imagenes_ventanas/,
        // in production they are fetched from the external FTP URL.
        $imagenesBase64 = [];
        $imageBaseUrl = rtrim(env('IMAGENES_BASE_URL', 'https://vialum.cl/laravelupload/imagenes_cotizaciones'), '/');
        foreach ($cotizacion->ventanas as $ventana) {
            if ($ventana->imagen) {
                try {
                    // 1) Check local storage first (works in local dev and if images were stored locally)
                    $localPath = storage_path('app/public/imagenes_ventanas/' . $ventana->imagen);
                    if (file_exists($localPath)) {
                        $data = file_get_contents($localPath);
                    } else {
                        // 2) Fallback: fetch from external FTP/URL (production with vialum.cl FTP)
                        $url = $imageBaseUrl . '/' . $ventana->imagen;
                        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
                        $data = @file_get_contents($url, false, $ctx);
                    }
                    if ($data !== false) {
                        $imagenesBase64[$ventana->id] = 'data:image/png;base64,' . base64_encode($data);
                    }
                } catch (\Exception $e) {
                    Log::warning('PDF: no se pudo cargar imagen para ventana ' . $ventana->id . ': ' . $e->getMessage());
                }
            }
        }

        $logoBase64 = null;
        try {
            $logoUrl = env('LOGO_URL', 'https://pub-7467388c2656489e9222164e85545a03.r2.dev/assets/logovialum.png');
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $logoData = @file_get_contents($logoUrl, false, $ctx);
            if ($logoData !== false) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
            }
        } catch (\Exception $e) {
            Log::warning('PDF: no se pudo cargar logo: ' . $e->getMessage());
        }

        $pdf = Pdf::loadView('cotizaciones.pdf', compact('cotizacion', 'imagenesBase64', 'logoBase64'))
            ->setOptions(['isPhpEnabled' => true]);

        return $pdf->download('cotizacion_' . $cotizacion->id . '.pdf');
    }

    public function generarOrdenTrabajo($id)
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

        $imagenesBase64 = [];
        $imageBaseUrl = rtrim(env('IMAGENES_BASE_URL', 'https://vialum.cl/laravelupload/imagenes_cotizaciones'), '/');
        foreach ($cotizacion->ventanas as $ventana) {
            if ($ventana->imagen) {
                try {
                    $localPath = storage_path('app/public/imagenes_ventanas/' . $ventana->imagen);
                    if (file_exists($localPath)) {
                        $data = file_get_contents($localPath);
                    } else {
                        $url = $imageBaseUrl . '/' . $ventana->imagen;
                        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
                        $data = @file_get_contents($url, false, $ctx);
                    }
                    if ($data !== false) {
                        $imagenesBase64[$ventana->id] = 'data:image/png;base64,' . base64_encode($data);
                    }
                } catch (\Exception $e) {
                    Log::warning('OT: no se pudo cargar imagen para ventana ' . $ventana->id . ': ' . $e->getMessage());
                }
            }
        }

        $pdf = Pdf::loadView('cotizaciones.orden-trabajo', compact('cotizacion', 'imagenesBase64'));

        return $pdf->download('OT_' . $cotizacion->id . '.pdf');
    }

    public function cambiarEstado(Request $request, $id)
    {
        $cotizacion = Cotizacion::with('estado')->findOrFail($id);
        $estadoActual = $cotizacion->estado->nombre;

        $transicionesPermitidas = [
            'Evaluación'    => ['Aprobada', 'Rechazada'],
            'Aprobada'      => ['En Producción', 'Rechazada'],
            'En Producción' => ['Entregada'],
            'Entregada'     => ['Facturada'],
        ];

        $nuevoNombre = $request->input('estado');
        $permitidos  = $transicionesPermitidas[$estadoActual] ?? [];

        if (!in_array($nuevoNombre, $permitidos)) {
            return response()->json([
                'message' => "No se puede cambiar de '{$estadoActual}' a '{$nuevoNombre}'."
            ], 422);
        }

        $nuevoEstado = EstadoCotizacion::where('nombre', $nuevoNombre)->firstOrFail();
        $cotizacion->update(['estado_cotizacion_id' => $nuevoEstado->id]);

        Log::info("✅ Cotización #{$id} cambiada de '{$estadoActual}' a '{$nuevoNombre}' por usuario " . auth()->id());

        return response()->json([
            'message' => "Estado actualizado a '{$nuevoNombre}'.",
            'estado'  => ['id' => $nuevoEstado->id, 'nombre' => $nuevoEstado->nombre],
        ]);
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
        $cotizaciones = Cotizacion::with([
            'cliente',
            'clienteFacturacion',
            'vendedor',
            'documentosFacturacion',
            'ventanas' => function($query) {
                $query->with([
                    'tipoVentana',
                    'color',
                    'productoVidrioProveedor' => function($q) {
                        $q->with(['producto', 'proveedor']);
                    }
                ]);
            },
            'detalles' => function($query) {
                $query->with([
                    'listaPrecio.producto',
                    'listaPrecio.color',
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

    // ─────────────────────────────────────────────────────────────
    // PARSEAR PDF WINPERFIL → devuelve datos estructurados
    // ─────────────────────────────────────────────────────────────
    public function parseWinperfil(Request $request)
    {
        $request->validate(['pdf' => 'required|file|mimes:pdf|max:20480']);

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($request->file('pdf')->getRealPath());
            $texto  = $pdf->getText();

            return response()->json(self::parsearTextoWinperfil($texto));
        } catch (\Exception $e) {
            Log::error('❌ Error parseando PDF WINPERFIL', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'No se pudo leer el PDF: ' . $e->getMessage()], 422);
        }
    }

    private static function parsearTextoWinperfil(string $text): array
    {
        $text  = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => $l !== ''
        ));

        $resultado = [
            'numero_presupuesto' => '',
            'fecha'              => '',
            'cliente_nombre'     => '',
            'items'              => [],
            'total_neto'         => 0,
        ];

        // ── Presupuesto Nº ──────────────────────────────────────
        foreach ($lines as $line) {
            if (preg_match('/Presupuesto\s+N[ºo°]?\s*[:\-]\s*(.+)/ui', $line, $m)) {
                $resultado['numero_presupuesto'] = trim($m[1]);
                break;
            }
        }

        // ── Fecha DD-MM-YYYY → YYYY-MM-DD ───────────────────────
        foreach ($lines as $line) {
            if (preg_match('/^Fecha:\s*(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/i', $line, $m)) {
                $resultado['fecha'] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                break;
            }
        }

        // ── Cliente: primera línea no vacía después de "OBRA:" ───
        foreach ($lines as $i => $line) {
            if (preg_match('/^OBRA:\s*$/i', $line)) {
                for ($j = $i + 1; $j < min($i + 5, count($lines)); $j++) {
                    if ($lines[$j] !== '' && !preg_match('/^[-\s]*\(/', $lines[$j])) {
                        $resultado['cliente_nombre'] = $lines[$j];
                        break;
                    }
                }
                break;
            }
        }

        // ── Items: buscar filas Vx cant precio CLP$ total CLP$ ──
        $itemMatches = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(V\d+[A-Za-z]?)\s+(\d+)\s+([\d\.]+)\s+CLP\$\s+([\d\.]+)\s+CLP\$/i', $line, $m)) {
                $itemMatches[] = [
                    'index'           => $i,
                    'tipo'            => $m[1],
                    'cantidad'        => (int) $m[2],
                    'precio_unitario' => (int) str_replace('.', '', $m[3]),
                    'total'           => (int) str_replace('.', '', $m[4]),
                ];
            }
        }

        // Para cada item, buscar hacia atrás su título, color y medida
        $ignorar = [
            '/GRAFICO/i',           // captura "GRAFICO", "DESCRIPCIÓNGRAFICO", etc.
            '/DESCRIPCI/i',         // captura "DESCRIPCIÓN" y variantes combinadas
            '/^TIPO\s+CANTIDAD/i',
            '/^DATOS DEL/i', '/^Vialum/i', '/^Balmaceda/i', '/^\s*\(/',
            '/^Rut:/i', '/^Tel[eé]/i', '/^Celular/i', '/^Fax/i',
            '/^Forma de/i', '/^OBRA:/i', '/página/ui',
            '/^[⦁•\-]\s/', '/^Presupuesto/i', '/^Fecha/i',
            '/^termopanel/i', '/^m\s*\(/i', '/^\d+[\.,]/i',
        ];

        foreach ($itemMatches as $match) {
            $titulo     = '';
            $serie      = '';
            $color      = '';
            $medida     = '';
            $superficie = '';

            for ($j = $match['index'] - 1; $j >= max(0, $match['index'] - 30); $j--) {
                $l = $lines[$j];

                // Parar si encontramos la línea de otro item (V2, V6A, etc.)
                if ($j < $match['index'] - 1 && preg_match('/^\s*V\d+[A-Za-z]?\s+\d+\s+[\d\.]+\s+CLP\$/i', $l)) {
                    break;
                }

                if (preg_match('/Serie:\s*(.+)/i',      $l, $m)) $serie      = trim($m[1]);
                if (preg_match('/Color:\s*(.+)/i',      $l, $m)) $color      = trim($m[1]);
                if (preg_match('/Medida:\s*(.+)/i',     $l, $m)) $medida     = trim($m[1]);
                if (preg_match('/Superficie:\s*(.+)/i', $l, $m)) $superficie = trim($m[1]);

                // Título: línea sin ":", empieza con mayúscula, no coincide con ignorar
                if (empty($titulo) && strlen($l) > 3 && !str_contains($l, ':') && preg_match('/^\p{Lu}/u', $l)) {
                    $skip = false;
                    foreach ($ignorar as $pat) {
                        if (preg_match($pat, $l)) { $skip = true; break; }
                    }
                    if (!$skip && !preg_match('/^\s*V\d+[A-Za-z]?/i', $l)) {
                        $titulo = strtoupper($l);
                    }
                }
            }

            // Formato: "V1 - TÍTULO, Serie, Color: X, Medida: X, Superficie: X"
            $partes = array_filter([
                $serie,
                $color      ? "Color: $color"           : '',
                $medida     ? "Medida: $medida"         : '',
                $superficie ? "Superficie: $superficie" : '',
            ]);
            $tituloDesc = $titulo ?: "VENTANA PVC";
            $desc = "{$match['tipo']} - {$tituloDesc}, " . implode(', ', $partes);
            $desc = rtrim($desc, ', ');

            $resultado['items'][] = [
                'descripcion'     => $desc,
                'cantidad'        => $match['cantidad'],
                'precio_unitario' => $match['precio_unitario'],
                'total'           => $match['total'],
            ];
        }

        // ── Total neto: número CLP$ justo antes de "Suma Total Neto" ─
        foreach ($lines as $i => $line) {
            if (preg_match('/Suma\s+Total\s+Neto/i', $line)) {
                for ($j = $i - 1; $j >= max(0, $i - 4); $j--) {
                    if (preg_match('/([\d\.]+)\s*CLP\$/i', $lines[$j], $m)) {
                        $resultado['total_neto'] = (int) str_replace('.', '', $m[1]);
                        break 2;
                    }
                    if (preg_match('/^([\d\.]{4,})$/', $lines[$j], $m)) {
                        $resultado['total_neto'] = (int) str_replace('.', '', $m[1]);
                        break 2;
                    }
                }
                break;
            }
        }

        return $resultado;
    }

    // ─────────────────────────────────────────────────────────────
    // IMPORTAR COTIZACIÓN DESDE WINPERFIL (PVC) + guardar PDF
    // ─────────────────────────────────────────────────────────────
    public function importarWinperfil(Request $request)
    {
        $request->validate([
            'cliente_id'              => 'required|exists:clientes,id',
            'fecha'                   => 'required|date',
            'items'                   => 'required|array|min:1',
            'items.*.descripcion'     => 'required|string',
            'items.*.cantidad'        => 'required|numeric|min:0.01',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'pdf'                     => 'nullable|file|mimes:pdf|max:20480',
        ]);

        try {
            DB::beginTransaction();

            $obs = 'Importado desde WINPERFIL (PVC)';
            if ($request->filled('numero_presupuesto')) {
                $obs .= ' — Presupuesto Nº: ' . $request->numero_presupuesto;
            }
            if ($request->filled('observaciones')) {
                $obs .= "\n" . $request->observaciones;
            }

            $total = collect($request->items)
                ->sum(fn($i) => ($i['cantidad'] ?? 0) * ($i['precio_unitario'] ?? 0));

            $estadoEvaluacion = EstadoCotizacion::where('nombre', 'like', '%valuaci%')->first();

            // Subir PDF a Cloudflare R2
            $adjuntoUrl = null;
            if ($request->hasFile('pdf')) {
                $file     = $request->file('pdf');
                $filename = 'winperfil/' . now()->format('Ymd_His') . '_' . uniqid() . '.pdf';
                \Illuminate\Support\Facades\Storage::disk('r2')->put($filename, file_get_contents($file->getRealPath()), 'public');
                $adjuntoUrl = rtrim(env('R2_PUBLIC_URL'), '/') . '/' . $filename;
            }

            $cotizacion = Cotizacion::create([
                'cliente_id'          => $request->cliente_id,
                'vendedor_id'         => auth()->id(),
                'fecha'               => $request->fecha,
                'estado_cotizacion_id'=> $estadoEvaluacion?->id ?? 1,
                'observaciones'       => $obs,
                'total'               => $total,
                'adjunto_winperfil'   => $adjuntoUrl,
            ]);

            foreach ($request->items as $item) {
                $cant   = $item['cantidad'] ?? 1;
                $precio = $item['precio_unitario'] ?? 0;
                \App\Models\CotizacionDetalle::create([
                    'cotizacion_id'   => $cotizacion->id,
                    'tipo_item'       => 'winperfil',
                    'descripcion'     => $item['descripcion'],
                    'cantidad'        => $cant,
                    'precio_unitario' => $precio,
                    'total'           => $cant * $precio,
                ]);
            }

            DB::commit();

            return response()->json([
                'success'       => true,
                'cotizacion_id' => $cotizacion->id,
                'message'       => 'Cotización WINPERFIL importada correctamente',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error importando WINPERFIL', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // ACTUALIZAR COTIZACIÓN WINPERFIL EXISTENTE
    // ─────────────────────────────────────────────────────────────
    public function actualizarWinperfil(Request $request, $id)
    {
        $request->validate([
            'cliente_id'              => 'required|exists:clientes,id',
            'fecha'                   => 'required|date',
            'items'                   => 'required|array|min:1',
            'items.*.descripcion'     => 'required|string',
            'items.*.cantidad'        => 'required|numeric|min:0.01',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'pdf'                     => 'nullable|file|mimes:pdf|max:20480',
        ]);

        try {
            DB::beginTransaction();

            $cotizacion = Cotizacion::findOrFail($id);

            $total = collect($request->items)->sum(fn($i) =>
                ($i['cantidad'] ?? 1) * ($i['precio_unitario'] ?? 0)
            );

            $obs = $request->observaciones ?? $cotizacion->observaciones;

            // Subir nuevo PDF si fue adjuntado
            $adjuntoUrl = $cotizacion->adjunto_winperfil;
            if ($request->hasFile('pdf')) {
                $file     = $request->file('pdf');
                $filename = 'winperfil/' . now()->format('Ymd_His') . '_' . uniqid() . '.pdf';
                \Illuminate\Support\Facades\Storage::disk('r2')->put($filename, file_get_contents($file->getRealPath()), 'public');
                $adjuntoUrl = rtrim(env('R2_PUBLIC_URL'), '/') . '/' . $filename;
            }

            $cotizacion->update([
                'cliente_id'        => $request->cliente_id,
                'fecha'             => $request->fecha,
                'observaciones'     => $obs,
                'total'             => $total,
                'adjunto_winperfil' => $adjuntoUrl,
            ]);

            // Reemplazar todos los detalles winperfil
            $cotizacion->detalles()->where('tipo_item', 'winperfil')->delete();

            foreach ($request->items as $item) {
                $cant   = $item['cantidad'] ?? 1;
                $precio = $item['precio_unitario'] ?? 0;
                \App\Models\CotizacionDetalle::create([
                    'cotizacion_id'   => $cotizacion->id,
                    'tipo_item'       => 'winperfil',
                    'descripcion'     => $item['descripcion'],
                    'cantidad'        => $cant,
                    'precio_unitario' => $precio,
                    'total'           => $cant * $precio,
                ]);
            }

            DB::commit();

            return response()->json([
                'success'       => true,
                'cotizacion_id' => $cotizacion->id,
                'message'       => 'Cotización WINPERFIL actualizada correctamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

}
