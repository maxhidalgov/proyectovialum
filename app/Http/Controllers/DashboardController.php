<?php

namespace App\Http\Controllers;


use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{


public function ventasMensuales(Request $request)
{
    $token = '4845c098298dba6a64cf559dbecb555e310458d4';
    $baseUrl = 'https://api.bsale.cl/v1/';
    $mes = (int) $request->get('mes', now()->month);
    $anio = (int) $request->get('anio', now()->year);

    $inicio = Carbon::create($anio, $mes, 1)->startOfMonth()->timestamp;
    $fin = Carbon::create($anio, $mes, 1)->endOfMonth()->timestamp;

    $limit = 50;
    $offset = 0;
    $ventasPorCliente = [];
    $clientesCache = [];
    $porDia = collect();

    do {
        $response = Http::withHeaders(['access_token' => $token])
            ->get($baseUrl . 'documents.json', [
                'emissiondaterange' => "[$inicio,$fin]",
                'limit' => $limit,
                'offset' => $offset,
            ]);

        if ($response->failed()) {
            Log::error('Error al obtener documentos', ['body' => $response->body()]);
            break;
        }

        $items = $response->json()['items'] ?? [];

        foreach ($items as $doc) {
            $tipo = (int) ($doc['document_type']['id'] ?? 0);
            if (!in_array($tipo, [1, 2, 3, 4, 5])) continue;

            $clienteId = $doc['client']['id'] ?? 'sin_cliente_' . ($doc['id'] ?? uniqid());

            // Verifica si ya tenemos este cliente en caché
            if (!isset($clientesCache[$clienteId])) {
                $nombre = 'Consumidor Final';

                if (isset($doc['client']['href'])) {
                    $clienteResponse = Http::withHeaders(['access_token' => $token])
                        ->get($doc['client']['href']);

                    if ($clienteResponse->ok()) {
                        $clienteData = $clienteResponse->json();
                        $nombre = $clienteData['company']
                            ?? trim(($clienteData['firstName'] ?? '') . ' ' . ($clienteData['lastName'] ?? ''))
                            ?: 'Consumidor Final';
                    } else {
                        Log::warning("No se pudo obtener cliente $clienteId");
                    }
                }

                $clientesCache[$clienteId] = $nombre;
            }

            $nombre = $clientesCache[$clienteId];
            $monto = (float) $doc['totalAmount'];
            if ($tipo === 2) $monto *= -1;

            if (!isset($ventasPorCliente[$clienteId])) {
                $ventasPorCliente[$clienteId] = [
                    'cliente_id' => $clienteId,
                    'cliente' => $nombre,
                    'cantidad' => 0,
                    'total' => 0,
                ];
            }

            $ventasPorCliente[$clienteId]['cantidad'] += 1;
            $ventasPorCliente[$clienteId]['total'] += $monto;

            // Agrupación diaria
            $fecha = Carbon::createFromTimestamp($doc['emissionDate'])->toDateString();
            $porDia[$fecha] = ($porDia[$fecha] ?? 0) + $monto;
        }

        $offset += $limit;
    } while (count($items) === $limit);

    return response()->json([
        'clientes' => array_values($ventasPorCliente),
        'total_mes' => round(array_sum(array_column($ventasPorCliente, 'total'))),
        'cantidad' => array_sum(array_column($ventasPorCliente, 'cantidad')),
        'labels' => $porDia->keys(),
        'diarias' => $porDia->values(),
    ]);
}



public function comprasTercerosMensuales(Request $request)
{
    $token = '4845c098298dba6a64cf559dbecb555e310458d4';
    $baseUrl = 'https://api.bsale.cl/v1/';

    $mes = (int) $request->get('mes', now()->month);
    $anio = (int) $request->get('anio', now()->year);
    $proveedorId = $request->get('proveedor_id');

    $limit = 50;
    $offset = 0;
    $todosLosDocs = [];

    do {
        $params = [
            'year' => $anio,
            'month' => $mes,
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($proveedorId) {
            $params['clientId'] = $proveedorId;
        }

        $response = Http::withHeaders([
            'access_token' => $token,
        ])->get($baseUrl . 'third_party_documents.json', $params);

        $responseItems = $response->json()['items'] ?? [];

        // ✅ Filtrar solo facturas de compra (33) y notas de crédito (61)
        $filteredItems = array_filter($responseItems, function ($doc) {
            return in_array((int) ($doc['codeSii'] ?? 0), [33, 61]);
        });

        $todosLosDocs = array_merge($todosLosDocs, $filteredItems);
        $offset += $limit;

    } while (count($responseItems) === $limit);

    // Agrupación por día
    $porDia = collect($todosLosDocs)->groupBy(function ($doc) {
        return Carbon::createFromTimestamp($doc['emissionDate'])->toDateString();
    })->map(function ($docs) {
        return collect($docs)->sum(function ($doc) {
            $code = (int) ($doc['codeSii'] ?? 0);
            $monto = (float) ($doc['totalAmount'] ?? 0);
            return $code === 61 ? -1 * $monto : $monto;
        });
    });

    // Agrupación por proveedor
    $porProveedor = collect($todosLosDocs)
        ->groupBy(function ($doc) {
            return $doc['clientActivity'] ?? 'Proveedor Desconocido';
        })
        ->map(function ($docs, $nombreProveedor) {
            return [
                'proveedor' => $nombreProveedor,
                'cantidad' => count($docs),
                'total' => collect($docs)->sum(function ($doc) {
                    $code = (int) ($doc['codeSii'] ?? 0);
                    $monto = (float) ($doc['totalAmount'] ?? 0);
                    return $code === 61 ? -1 * $monto : $monto;
                }),
            ];
        })
        ->sortByDesc('total')
        ->values();

    return response()->json([
        'total_mes' => $porDia->sum(),
        'cantidad' => count($todosLosDocs),
        'diarias' => $porDia->values(),
        'labels' => $porDia->keys(),
        'proveedores' => $porProveedor,
    ]);
}



}
