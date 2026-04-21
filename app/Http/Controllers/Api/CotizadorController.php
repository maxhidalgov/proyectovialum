<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoMaterial;
use App\Models\Color;
use App\Models\TipoProducto;
use Illuminate\Http\Request;

class CotizadorController extends Controller
{
    public function tiposMaterial()
    {
        return TipoMaterial::select('id', 'nombre', 'margen')->get();
    }

    public function updateMargenMaterial(Request $request, $id)
    {
        $material = TipoMaterial::findOrFail($id);
        $material->margen = $request->validate(['margen' => 'required|numeric|min:0|max:0.99'])['margen'];
        $material->save();
        return response()->json($material);
    }

    public function colores()
    {
        return Color::select('id', 'nombre')->get();
    }

    public function tiposProducto()
    {
        return TipoProducto::select('id', 'nombre')->get();
    }
}
