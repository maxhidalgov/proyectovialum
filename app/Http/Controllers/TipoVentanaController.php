<?php

namespace App\Http\Controllers;

use App\Models\TipoVentana;
use Illuminate\Http\Request;

class TipoVentanaController extends Controller
{
    public function index()
    {
        // Trae los tipos de ventana con su relación al material
        return TipoVentana::with('material')->get();
    }
}