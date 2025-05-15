<?php

namespace App\Http\Controllers;

use App\Models\TipoProducto;

class TipoProductoController extends Controller
{
    public function index()
    {
        return TipoProducto::all();
    }
}