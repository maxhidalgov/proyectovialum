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
    $todosLosDocs = [];

    do {
        $response = Http::withHeaders([
            'access_token' => $token,
        ])->get($baseUrl . 'documents.json', [
            'emissiondaterange' => "[$inicio,$fin]",
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $items = $response->json()['items'] ?? [];
        $todosLosDocs = array_merge($todosLosDocs, $items);
        $offset += $limit;

    } while (count($items) === $limit); // sigue mientras lleguen 50

    // (Opcional) filtra documentos válidos por tipo
        $ventas = collect($todosLosDocs)->filter(function ($doc) {
        return in_array((int) $doc['document_type']['id'], [1, 2, 3, 4, 5]); // adapta a tus tipos reales
    });


    // Agrupa por fecha
    $porDia = $ventas->groupBy(function ($doc) {
    return Carbon::createFromTimestamp($doc['emissionDate'])->toDateString();
    })->map(function ($docs) {
        return collect($docs)->sum(function ($d) {
            $tipo = (int) $d['document_type']['id'];
            $monto = $d['totalAmount'];
            return $tipo === 2 ? -1 * $monto : $monto; // notas de crédito descuentan
        });
    });

    $tipos = collect($todosLosDocs)->pluck('document_type')->unique('id')->values();

    // Para debug:
    foreach ($tipos as $tipo) {
        Log::info('Tipo:', [
            'id' => $tipo['id'],
            'href' => $tipo['href'],
        ]);
    }


    return response()->json([
        'total_mes' => $porDia->sum(),
        'cantidad' => $ventas->count(),
        'diarias' => $porDia->values(),
        'labels' => $porDia->keys(),
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
