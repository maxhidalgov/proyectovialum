<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\BsaleClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BsaleClientController extends Controller
{
    protected $bsaleService;

    public function __construct(BsaleClientService $bsaleService)
    {
        $this->bsaleService = $bsaleService;
    }

    public function index()
    {
        try {
            $clients = $this->bsaleService->getClients();
            return response()->json($clients);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener clientes', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'firstName', 'lastName', 'company', 'code', 'email',
            'phone', 'city', 'municipality', 'address', 'activity'
        ]);

        try {
            // Crear cliente en Bsale
            $response = Http::withHeaders([
                'access_token' => config('services.bsale.token'),
            ])->post('https://api.bsale.io/v1/clients.json', $data);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Error al crear cliente en Bsale',
                    'message' => $response->body(),
                ], 500);
            }

            $clienteBsale = $response->json();

            // Guardar en base de datos local
            $clienteLocal = Cliente::create([
                'first_name'     => $clienteBsale['firstName'] ?? '',
                'last_name'      => $clienteBsale['lastName'] ?? '',
                'razon_social'   => $clienteBsale['company'] ?? '',
                'identification' => $clienteBsale['code'] ?? '',
                'email'          => $clienteBsale['email'] ?? '',
                'phone'          => $clienteBsale['phone'] ?? '',
                'address'        => $clienteBsale['address'] ?? '',
                'giro'           => $clienteBsale['activity'] ?? '',
                'ciudad'         => $clienteBsale['city'] ?? '',
                'comuna'         => $clienteBsale['municipality'] ?? '',
                'tipo_cliente'   => $clienteBsale['companyOrPerson'] == 1 ? 'empresa' : 'persona',
            ]);

            return response()->json(['cliente' => $clienteLocal], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear cliente',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
