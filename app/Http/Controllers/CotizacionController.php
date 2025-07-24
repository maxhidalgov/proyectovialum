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

    try {
        DB::beginTransaction();

        // Crear cotización
        $cotizacion = Cotizacion::create([
            'cliente_id' => $request->cliente_id,
            'vendedor_id' => $request->vendedor_id,
            'fecha' => $request->fecha,
            'estado_cotizacion_id' => $request->estado_cotizacion_id,
            'observaciones' => $request->observaciones,
            'total' => collect($request->ventanas)->sum('precio'),
        ]);

        // Guardar ventanas y mantener referencia
        $ventanasGuardadas = [];

        foreach ($request->ventanas as $ventana) {
            $ventanasGuardadas[] = $cotizacion->ventanas()->create([
                'tipo_ventana_id' => $ventana['tipo_ventana_id'],
                'ancho' => $ventana['ancho'],
                'alto' => $ventana['alto'],
                'cantidad' => $ventana['cantidad'] ?? 1,
                'color_id' => $ventana['color_id'],
                'producto_vidrio_proveedor_id' => $ventana['producto_vidrio_proveedor_id'],
                'costo' => $ventana['costo'] ?? 0,
                'precio' => $ventana['precio'] ?? 0,
            ]);
        }

        // Guardar imágenes si vienen en el request
        if ($request->has('imagenes_ventanas')) {
            foreach ($request->imagenes_ventanas as $index => $base64) {
                if (!$base64 || !str_starts_with($base64, 'data:image/png;base64,')) continue;

                $image = str_replace('data:image/png;base64,', '', $base64);
                $image = str_replace(' ', '+', $image);

                $imageName = 'cotizacion_' . $cotizacion->id . '_ventana_' . $index . '_' . time() . '.png';

                // Subir al servidor por FTP (configurado en config/filesystems.php)
                Storage::disk('ftp_cpanel')->put($imageName, base64_decode($image));

                if (isset($ventanasGuardadas[$index])) {
                    $ventanasGuardadas[$index]->update([
                        'imagen' => $imageName // O puedes guardar la URL completa si prefieres
                    ]);
                    Log::info("✅ Imagen asociada a ventana ID {$ventanasGuardadas[$index]->id}");
                } else {
                    Log::warning("⚠️ No se encontró ventana para imagen en índice $index");
                }
            }
        }
        DB::commit();
        return response()->json(['message' => 'Cotización guardada correctamente'], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('❌ Error al guardar cotización: ' . $e->getMessage());
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
        'vendedor',
        'ventanas.tipoVentana',
        'ventanas',
        'ventanas.color',
        'ventanas.productoVidrioProveedor.producto',
        'ventanas.productoVidrioProveedor.proveedor',
        'estado',
      
    ])->findOrFail($id);


    // Calcular precio_total por ventana
    $cotizacion->ventanas->transform(function ($ventana) {
        $ventana->precio_total = ($ventana->precio ?? 0) * ($ventana->cantidad ?? 1);
        return $ventana;
    });

    // Calcular total de la cotización
    $cotizacion->total_general = $cotizacion->ventanas->sum('precio_total');
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
        $cotizacion = Cotizacion::with('ventanas')->findOrFail($id);

        DB::transaction(function () use ($request, $cotizacion) {
            $cotizacion->update([
                'cliente_id' => $request->cliente_id,
                'fecha' => $request->fecha,
                'estado_cotizacion_id' => $request->estado_cotizacion_id,
                'observaciones' => $request->observaciones,
            ]);

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


}
