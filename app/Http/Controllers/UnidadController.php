<?php

namespace App\Http\Controllers;

use App\Models\Unidad;

class UnidadController extends Controller
{
    public function index()
    {
        return Unidad::all();
    }
}