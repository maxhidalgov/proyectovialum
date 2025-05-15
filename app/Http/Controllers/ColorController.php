<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Color;

class ColorController extends Controller {
    // Obtener todos los colores
    public function index() {
        return response()->json(Color::all());
    }

    // Crear un nuevo color
    public function store(Request $request) {
        $request->validate([
            'nombre' => 'required|string|unique:colores,nombre|max:255'
        ]);

        $color = Color::create(['nombre' => $request->nombre]);

        return response()->json([
            'message' => 'Color agregado correctamente',
            'data' => $color
        ], 201);
    }
}