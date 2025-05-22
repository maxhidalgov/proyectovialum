<?php

namespace App\Http\Controllers;

use App\Models\Ventana;
use Illuminate\Http\Request;

class VentanaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Ventana $ventana)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ventana $ventana)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $ventana = Ventana::findOrFail($id);
        $ventana->update([
            'ancho' => $request->ancho,
            'alto' => $request->alto,
            'color_id' => $request->color_id,
            'producto_vidrio_proveedor_id' => $request->producto_vidrio_proveedor_id,
            'costo' => $request->costo,
            'precio' => $request->precio,
            // otros campos si es necesario
        ]);

        return response()->json(['message' => 'Ventana actualizada']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ventana $ventana)
    {
        //
    }
}
