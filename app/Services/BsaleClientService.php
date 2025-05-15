<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BsaleClientService
{
    protected $token;
    protected $baseUrl;

    public function __construct()
    {
        $this->token = config('services.bsale.token');
        $this->baseUrl = 'https://api.bsale.io/v1'; // Puedes ajustar esto si usas sandbox
    }

    public function getClients($limit = 250, $offset = 0)
    {
        $response = Http::withHeaders([
            'access_token' => $this->token,
        ])->get("{$this->baseUrl}/clients.json", [
            'limit' => $limit,
            'offset' => $offset
        ]);
    
        if ($response->successful()) {
            $json = $response->json();
    
            // Limpieza de cada cliente
            $items = collect($json['items'] ?? [])->map(function ($item) {
                return [
                    'firstName'      => $item['firstName'] ?? '',
                    'lastName'       => $item['lastName'] ?? '',
                    'email'          => $item['email'] ?? '',
                    'identification' => $item['code'] ?? '',
                    'tipo_cliente'   => $item['companyOrPerson'] == 1 ? 'empresa' : 'persona',
                    'razon_social'   => $item['company'] ?? '',
                    'giro'           => $item['activity'] ?? '',
                    'ciudad'         => $item['city'] ?? '',
                    'comuna'         => $item['municipality'] ?? '',
                    'address'        => $item['address'] ?? '',
                    'phone'          => $item['phone'] ?? '',
                ];
            })->values()->toArray();
    
            return [
                'items' => $items,
                'count' => $json['count'] ?? 0,
                'limit' => $json['limit'] ?? $limit,
                'offset' => $json['offset'] ?? $offset,
            ];
        }
    
        throw new \Exception("Error al obtener clientes desde Bsale");
    }


    public function crearCliente(array $data)
{
    $payload = [
        'firstName'         => $data['firstName'] ?? '',
        'lastName'          => $data['lastName'] ?? '',
        'company'           => $data['company'] ?? '',
        'code'              => $data['code'] ?? '', // RUT
        'email'             => $data['email'] ?? '',
        'phone'             => $data['phone'] ?? '',
        'address'           => $data['address'] ?? '',
        'city'              => $data['city'] ?? '',
        'municipality'      => $data['municipality'] ?? '',
        'activity'          => $data['activity'] ?? '',
        'note'              => $data['note'] ?? '',
        'facebook'          => $data['facebook'] ?? '',
        'twitter'           => $data['twitter'] ?? '',
        'hasCredit'         => $data['hasCredit'] ?? 0,
        'maxCredit'         => $data['maxCredit'] ?? 0,
        'accumulatePoints'  => $data['accumulatePoints'] ?? 0,
        'dynamicAttributes' => $data['dynamicAttributes'] ?? null,
    ];

    $response = Http::withHeaders([
        'access_token' => $this->token,
        'Content-Type' => 'application/json',
    ])->post("{$this->baseUrl}/clients.json", $payload);

    if ($response->successful()) {
        return $response->json();
    }

    throw new \Exception("❌ Error al crear cliente en Bsale: " . $response->body());
}

}
