<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;
use App\Models\Ventana;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class CotizacionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
{
    $cotizaciones = Cotizacion::with(['cliente', 'vendedor', 'ventanas.tipoVentana'])
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

        $cotizacion = Cotizacion::create([
            'cliente_id' => $request->cliente_id,
            'vendedor_id' => $request->vendedor_id,
            'fecha' => $request->fecha,
            'estado' => $request->estado ?? 'Evaluación',
            'observaciones' => $request->observaciones,
            'total' => collect($request->ventanas)->sum('precio'),
        ]);

        foreach ($request->ventanas as $ventana) {
            $cotizacion->ventanas()->create([
                'tipo_ventana_id' => $ventana['tipo_ventana_id'],
                'ancho' => $ventana['ancho'],
                'alto' => $ventana['alto'],
                'color_id' => $ventana['color_id'],
                'producto_vidrio_proveedor_id' => $ventana['producto_vidrio_proveedor_id'],
                'costo' => $ventana['costo'],
                'precio' => $ventana['precio'],
            ]);
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
        'ventanas.productoVidrioProveedor.proveedor'
    ])->findOrFail($id);

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
    $cotizacion = Cotizacion::findOrFail($id);
    $cotizacion->update([
        'fecha' => $request->fecha,
        'estado' => $request->estado,
        'observaciones' => $request->observaciones,
    ]);

    return response()->json(['message' => 'Cotización actualizada']);
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
    $cotizacion = Cotizacion::with(['cliente', 'vendedor', 'ventanas.tipoVentana'])->findOrFail($id);

    $pdf = Pdf::loadView('cotizaciones.pdf', compact('cotizacion'));

    return $pdf->download('cotizacion_' . $cotizacion->id . '.pdf');
    }

    public function duplicar($id)
{
    $original = Cotizacion::with('ventanas')->findOrFail($id);

    $nueva = Cotizacion::create([
    'cliente_id' => $original->cliente_id,
    'vendedor_id' => $original->vendedor_id,
    'fecha' => now()->toDateString(),
    'estado' => 'Evaluación',
    'observaciones' => $original->observaciones,
    'total' => $original->total,
    'origen_id' => $original->id, // 👈 aquí
    ]); 
    foreach ($original->ventanas as $ventana) {
        $nueva->ventanas()->create([
            'tipo_ventana_id' => $ventana->tipo_ventana_id,
            'ancho' => $ventana->ancho,
            'alto' => $ventana->alto,
            'color_id' => $ventana->color_id,
            'producto_vidrio_proveedor_id' => $ventana->producto_vidrio_proveedor_id,
            'costo' => $ventana->costo,
            'precio' => $ventana->precio,
        ]);
    }

    return response()->json(['id' => $nueva->id, 'message' => 'Cotización duplicada']);
}

}
