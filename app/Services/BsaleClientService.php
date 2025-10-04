<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BsaleClientService
{
    protected $token;
    protected $baseUrl;

    public function __construct()
    {
        $this->token = config('services.bsale.token');
        $this->baseUrl = 'https://api.bsale.io/v1'; // Puedes ajustar esto si usas sandbox
    }

    public function getClients($limit = 50, $offset = 0)
    {
        // Si se solicita más de 250, paginar automáticamente para obtener todos
        if ($limit > 250) {
            return $this->getAllClients();
        }

        $response = Http::withHeaders([
            'access_token' => $this->token,
        ])->get("{$this->baseUrl}/clients.json", [
            'limit' => min($limit, 50), // Máximo 50 según documentación
            'offset' => $offset
        ]);
    
        if ($response->successful()) {
            $json = $response->json();
            $items = $this->processClientItems($json['items'] ?? []);

            return [
                'items' => $items,
                'count' => $json['count'] ?? 0,
                'limit' => $json['limit'] ?? $limit,
                'offset' => $json['offset'] ?? $offset,
            ];
        }
    
        throw new \Exception("Error al obtener clientes desde Bsale: " . $response->body());
    }

    /**
     * Obtiene TODOS los clientes de Bsale paginando automáticamente
     */
    private function getAllClients()
    {
        $allItems = [];
        $offset = 0;
        $limit = 50; // Máximo permitido por Bsale
        $totalCount = 0;

        do {
            $response = Http::withHeaders([
                'access_token' => $this->token,
            ])->get("{$this->baseUrl}/clients.json", [
                'limit' => $limit,
                'offset' => $offset
            ]);

            if (!$response->successful()) {
                throw new \Exception("Error paginando clientes desde Bsale: " . $response->body());
            }

            $json = $response->json();
            $items = $json['items'] ?? [];
            $totalCount = $json['count'] ?? 0;

            // Procesar y agregar items de esta página
            $processedItems = $this->processClientItems($items);
            $allItems = array_merge($allItems, $processedItems);

            $offset += $limit;

            // Continuar mientras haya más items por obtener
        } while (count($items) === $limit && $offset < $totalCount);

        return [
            'items' => $allItems,
            'count' => count($allItems),
            'total_available' => $totalCount,
            'limit' => count($allItems),
            'offset' => 0,
        ];
    }

    /**
     * Procesa y limpia los datos de clientes de Bsale
     */
    private function processClientItems($items)
    {
        return collect($items)->map(function ($item) {
            return [
                'id'             => $item['id'] ?? null,
                'firstName'      => $item['firstName'] ?? '',
                'lastName'       => $item['lastName'] ?? '',
                'company'        => $item['company'] ?? '',
                'email'          => $item['email'] ?? '',
                'identification' => $item['code'] ?? '',
                'companyOrPerson' => $item['companyOrPerson'] ?? 0,
                'tipo_cliente'   => $item['companyOrPerson'] == 1 ? 'empresa' : 'persona',
                'razon_social'   => $item['company'] ?: trim(($item['firstName'] ?? '') . ' ' . ($item['lastName'] ?? '')),
                'giro'           => $item['activity'] ?? '',
                'city'           => $item['city'] ?? '',
                'municipality'   => $item['municipality'] ?? '',
                'address'        => $item['address'] ?? '',
                'phone'          => $item['phone'] ?? '',
                // Agregar campos adicionales de Bsale
                'displayName'    => $item['company'] 
                    ? $item['company'] 
                    : trim(($item['firstName'] ?? '') . ' ' . ($item['lastName'] ?? '')),
            ];
        })->values()->toArray();
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

    public function getOffices($limit = 25, $offset = 0)
    {
        $response = Http::withHeaders([
            'access_token' => $this->token,
        ])->get("{$this->baseUrl}/offices.json", [
            'limit' => $limit,
            'offset' => $offset,
            'state' => 0 // Solo oficinas activas
        ]);

        if ($response->successful()) {
            $json = $response->json();

            // Formatear los datos de las oficinas
            $items = collect($json['items'] ?? [])->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'description' => $item['description'] ?? '',
                    'address' => $item['address'] ?? '',
                    'city' => $item['city'] ?? '',
                    'municipality' => $item['municipality'] ?? '',
                    'isVirtual' => $item['isVirtual'] ?? false,
                    'state' => $item['state']
                ];
            })->values()->toArray();

            return [
                'items' => $items,
                'count' => $json['count'] ?? 0,
                'limit' => $json['limit'] ?? $limit,
                'offset' => $json['offset'] ?? $offset,
            ];
        }

        throw new \Exception("Error al obtener oficinas desde Bsale: " . $response->body());
    }

    public function getDocumentTypes($limit = 25, $offset = 0)
    {
        $response = Http::withHeaders([
            'access_token' => $this->token,
        ])->get("{$this->baseUrl}/document_types.json", [
            'limit' => $limit,
            'offset' => $offset
        ]);

        if ($response->successful()) {
            $json = $response->json();

            // Formatear los tipos de documento
            $items = collect($json['items'] ?? [])->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'codeSii' => $item['codeSii'] ?? '',
                    'description' => $item['description'] ?? ''
                ];
            })->values()->toArray();

            return [
                'items' => $items,
                'count' => $json['count'] ?? 0,
                'limit' => $json['limit'] ?? $limit,
                'offset' => $json['offset'] ?? $offset,
            ];
        }

        throw new \Exception("Error al obtener tipos de documento desde Bsale: " . $response->body());
    }

    /**
     * Busca clientes de manera simple y efectiva
     */
    public function searchClients($query, $limit = 50)
    {
        Log::info("🔍 Buscando clientes con query: {$query}");
        
        try {
            // Buscar en las primeras 2 páginas (100 clientes) y filtrar
            $allResults = collect();
            $pageSize = 50;
            $maxPages = 2;
            
            for ($page = 0; $page < $maxPages; $page++) {
                $offset = $page * $pageSize;
                
                $response = Http::withHeaders([
                    'access_token' => $this->token,
                ])->get("{$this->baseUrl}/clients.json", [
                    'limit' => $pageSize,
                    'offset' => $offset
                ]);
                
                if (!$response->successful()) {
                    Log::error("❌ Error en página {$page}: " . $response->body());
                    break;
                }
                
                $json = $response->json();
                $items = $this->processClientItems($json['items'] ?? []);
                
                if (empty($items)) {
                    break;
                }
                
                $allResults = $allResults->merge($items);
            }
            
            // Filtrar resultados localmente
            $searchTerm = strtolower(trim($query));
            $filteredResults = $allResults->filter(function ($client) use ($searchTerm) {
                $razocSocial = strtolower($client['razon_social'] ?? '');
                $firstName = strtolower($client['firstName'] ?? '');
                $lastName = strtolower($client['lastName'] ?? '');
                $company = strtolower($client['company'] ?? '');
                $identification = strtolower($client['identification'] ?? '');
                $email = strtolower($client['email'] ?? '');
                
                return str_contains($razocSocial, $searchTerm) ||
                       str_contains($firstName, $searchTerm) ||
                       str_contains($lastName, $searchTerm) ||
                       str_contains($company, $searchTerm) ||
                       str_contains($identification, $searchTerm) ||
                       str_contains($email, $searchTerm);
            })->take($limit);
            
            Log::info("✅ Encontrados {$filteredResults->count()} clientes de {$allResults->count()} totales");
            
            return [
                'items' => $filteredResults->values()->toArray(),
                'count' => $filteredResults->count(),
                'query' => $query,
                'total_searched' => $allResults->count()
            ];
            
        } catch (\Exception $e) {
            Log::error("❌ Error buscando clientes: " . $e->getMessage());
            
            return [
                'items' => [],
                'count' => 0,
                'query' => $query,
                'error' => $e->getMessage()
            ];
        }
    }


}
