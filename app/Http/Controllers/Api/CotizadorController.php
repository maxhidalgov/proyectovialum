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
        return TipoMaterial::select('id', 'nombre')->get();
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
