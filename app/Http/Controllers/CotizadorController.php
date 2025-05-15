<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CalculoVentanaService;
use Illuminate\Support\Facades\Log;

class CotizadorController extends Controller
{
    protected $calculoVentanaService;

    public function __construct(CalculoVentanaService $calculoVentanaService)
    {
        $this->calculoVentanaService = $calculoVentanaService;
    }

    public function calcularMateriales(Request $request)
{
    // Validar los datos recibidos
    $request->validate([
        'tipo' => 'required|integer',
        'alto' => 'required|numeric',
        'ancho' => 'required|numeric',
        'productoVidrio' => 'required|integer',
        // Añadir más validaciones si es necesario
    ]);
    Log::debug('Datos recibidos en calcularMateriales:', $request->all());

    $ventana = $request->all();
    $resultado = CalculoVentanaService::calcularMateriales($ventana);

    return response()->json($resultado);
}
    
}
