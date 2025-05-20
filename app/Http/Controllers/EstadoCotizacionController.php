<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EstadoCotizacion;

class EstadoCotizacionController extends Controller
{
    public function index()
{
    return EstadoCotizacion::all();
}
}
