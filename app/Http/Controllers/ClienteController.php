<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use App\Services\BsaleClientService;
use Illuminate\Http\JsonResponse;

class ClienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Cliente::all();
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
    $cliente = Cliente::create([
        'tipo_cliente'   => $request->tipo_cliente,
        'razon_social'   => $request->razon_social,
        'giro'           => $request->giro,
        'ciudad'         => $request->ciudad,
        'comuna'         => $request->comuna,
        'address'          => $request->address,
        'first_name'     => $request->first_name,
        'last_name'      => $request->last_name,
        'email'          => $request->email,
        'identification' => $request->identification,
        'phone'       => $request->phone,
    ]);

    return response()->json([
        'message' => 'Cliente guardado exitosamente',
        'cliente' => $cliente,
    ]);
}

    /**
     * Display the specified resource.
     */
    public function show(Cliente $cliente)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cliente $cliente)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cliente $cliente)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cliente $cliente)
    {
        //
    }

    public function importarTodos(BsaleClientService $bsale): JsonResponse
{
    $limit = 25;
    $offset = 0;
    $importados = 0;
    $totalClientes = 0;

    try {
        do {
            $response = $bsale->getClients($limit, $offset);

            $clientes = $response['items'] ?? [];
            $count = $response['count'] ?? 0;
            $totalClientes = $count;
            $obtenidos = count($clientes);

            foreach ($clientes as $c) {
                $query = Cliente::query();

                if (!empty($c['identification'])) {
                    $query->orWhere('identification', $c['identification']);
                }

                if (!empty($c['email'])) {
                    $query->orWhere('email', $c['email']);
                }

                $existe = $query->first();

                if (!$existe) {
                    Cliente::create([
                        'first_name'     => $c['firstName'],
                        'last_name'      => $c['lastName'],
                        'email'          => $c['email'],
                        'identification' => $c['identification'],
                        'telefono'       => $c['telefono'] ?? $c['phone'] ?? null,
                        'direccion'      => $c['direccion'] ?? $c['address'] ?? null,
                        'tipo_cliente'   => $c['tipo_cliente'] ?? null,
                        'razon_social'   => $c['razon_social'] ?? $c['company'] ?? null,
                        'giro'           => $c['giro'] ?? $c['activity'] ?? null,
                        'ciudad'         => $c['ciudad'] ?? $c['city'] ?? null,
                        'comuna'         => $c['comuna'] ?? $c['municipality'] ?? null,
                    ]);
                    $importados++;
                }
            }

            $offset += $limit;

        } while ($offset < $totalClientes); // seguimos hasta traer todos

        return response()->json([
            'message' => "✅ Se importaron $importados clientes de $totalClientes posibles.",
        ]);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
public function crearClienteBsale(Request $request, BsaleClientService $bsale)
{
    $validated = $request->validate([
        'firstName'         => 'nullable|string',
        'lastName'          => 'nullable|string',
        'company'           => 'nullable|string',
        'code'              => 'nullable|string',
        'email'             => 'nullable|email',
        'phone'             => 'nullable|string',
        'address'           => 'nullable|string',
        'city'              => 'nullable|string',
        'municipality'      => 'nullable|string',
        'activity'          => 'nullable|string',
        'note'              => 'nullable|string',
        'facebook'          => 'nullable|string',
        'twitter'           => 'nullable|string',
        'hasCredit'         => 'nullable|boolean',
        'maxCredit'         => 'nullable|numeric',
        'accumulatePoints'  => 'nullable|boolean',
        'dynamicAttributes' => 'nullable|array',
    ]);

    try {
        $clienteBsale = $bsale->crearCliente($validated);

        // Guardamos en tu base también:
        $cliente = Cliente::create([
            'first_name'     => $clienteBsale['firstName'] ?? '',
            'last_name'      => $clienteBsale['lastName'] ?? '',
            'email'          => $clienteBsale['email'] ?? '',
            'identification' => $clienteBsale['code'] ?? '',
            'telefono'       => $clienteBsale['phone'] ?? '',
            'direccion'      => $clienteBsale['address'] ?? '',
            'tipo_cliente'   => $clienteBsale['companyOrPerson'] == 1 ? 'empresa' : 'persona',
            'razon_social'   => $clienteBsale['company'] ?? '',
            'giro'           => $clienteBsale['activity'] ?? '',
            'ciudad'         => $clienteBsale['city'] ?? '',
            'comuna'         => $clienteBsale['municipality'] ?? '',
        ]);

        return response()->json([
            'message' => '✅ Cliente creado en Bsale y guardado localmente.',
            'cliente' => $cliente,
        ]);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function buscar(Request $request)
{
    $query = $request->input('q');

    $clientes = \App\Models\Cliente::where('razon_social', 'like', "%{$query}%")
        ->orWhere('first_name', 'like', "%{$query}%")
        ->orWhere('last_name', 'like', "%{$query}%")
        ->orWhere('identification', 'like', "%{$query}%")
        ->limit(20)
        ->get();

    return response()->json($clientes);
}


}
