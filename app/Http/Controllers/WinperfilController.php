<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Cliente;
use App\Models\EstadoCotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WinperfilController extends Controller
{
    private string $baseUrl;
    private int    $empresa;
    private string $serieDefault;

    public function __construct()
    {
        $this->baseUrl      = rtrim(config('services.winperfil.url', 'http://localhost:2024'), '/');
        $this->empresa      = (int) config('services.winperfil.empresa', 1);
        $this->serieDefault = config('services.winperfil.serie', 'A');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Conectividad
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Debug: devuelve la respuesta cruda de Winperfil.
     * ?numero=X  → llama /erp/presupuesto?numero=X&imagen=true (detalle completo con SVG)
     * ?endpoint=presupuestos → listado (sin imagen, limitado a 2)
     */
    public function debugRaw(Request $request)
    {
        $serie  = $request->get('serie', $this->serieDefault);
        $numero = $request->get('numero');

        try {
            if ($numero) {
                // Modo detalle: muestra todos los campos reales del presupuesto + detalles
                $params = [
                    'empresa' => $this->empresa,
                    'serie'   => $serie,
                    'numero'  => $numero,
                    'imagen'  => 'true',
                ];
                $url = "{$this->baseUrl}/erp/presupuesto";
                $res = Http::timeout(30)->get($url, $params);
                $body = $res->json();

                // Extraer claves únicas de cabecera y detalle para diagnóstico
                $oferta       = $body['ofertas'][0] ?? null;
                $cabKeys      = $oferta ? array_keys($oferta['cabecera'] ?? []) : [];
                $detKeys      = $oferta && !empty($oferta['detalle'])
                                    ? array_keys($oferta['detalle'][0] ?? [])
                                    : [];
                $tieneGrafico = $oferta && !empty($oferta['detalle'])
                                    ? collect($oferta['detalle'])->filter(fn($d) => !empty($d['GRAFICO_SVG_BASE64']))->count()
                                    : 0;

                return response()->json([
                    'status'           => $res->status(),
                    'url'              => $url,
                    'params'           => $params,
                    'cabecera_keys'    => $cabKeys,
                    'detalle_keys'     => $detKeys,
                    'detalle_count'    => count($oferta['detalle'] ?? []),
                    'graficos_count'   => $tieneGrafico,
                    'body'             => $body,
                ]);
            }

            // Modo listado
            $endpoint = $request->get('endpoint', 'presupuestos');
            $desde    = $request->get('desde', now()->startOfMonth()->format('Y-m-d'));
            $hasta    = $request->get('hasta', now()->format('Y-m-d'));
            $params   = [
                'empresa'        => $this->empresa,
                'serie'          => $serie,
                'fechaInicio'    => $desde,
                'fechaFin'       => $hasta,
                'detalle'        => 'true',
                'imagen'         => 'true',
                'itemsPorPagina' => 2,
            ];
            $url = "{$this->baseUrl}/erp/{$endpoint}";
            $res = Http::timeout(30)->get($url, $params);

            return response()->json([
                'status' => $res->status(),
                'url'    => $url,
                'params' => $params,
                'body'   => $res->json() ?? $res->body(),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    /**
     * Prueba la conexión con la API de Winperfil.
     */
    public function testConexion()
    {
        try {
            $res = Http::connectTimeout(15)->timeout(20)->get("{$this->baseUrl}/erp/clientes", [
                'empresa'        => $this->empresa,
                'pagina'         => 0,
                'itemsPorPagina' => 1,
            ]);

            if ($res->successful()) {
                return response()->json([
                    'ok'      => true,
                    'mensaje' => 'Conexión exitosa con Winperfil',
                    'url'     => $this->baseUrl,
                ]);
            }

            return response()->json([
                'ok'      => false,
                'mensaje' => "Winperfil respondió con código {$res->status()}",
                'url'     => $this->baseUrl,
            ], 502);

        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'No se pudo conectar con Winperfil: ' . $e->getMessage(),
                'url'     => $this->baseUrl,
            ], 503);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Proxy — lectura directa desde Winperfil (sin persistir)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Lista presupuestos desde Winperfil (proxy).
     * Respuesta real: { _metadata, datos: [ {cabecera:{...}, detalle:[...]} ] }
     */
    public function getPresupuestos(Request $request)
    {
        $serie = $request->get('serie', $this->serieDefault);
        $desde = $request->get('desde', now()->startOfMonth()->format('Y-m-d'));
        $hasta = $request->get('hasta', now()->format('Y-m-d'));

        try {
            $params = [
                'empresa'     => $this->empresa,
                'serie'       => $serie,
                'fechaInicio' => $desde,
                'fechaFin'    => $hasta,
                'detalle'     => 'true',
            ];

            $items = $this->fetchAllPages('presupuestos', $params);

            // Enriquecer con estado de sync local
            // El número puede venir como PRESUPUESTO_NUMERO (mayúsc) o numfactura (minúsc)
            $getNumero = fn($p) => $p['PRESUPUESTO_NUMERO'] ?? $p['numfactura'] ?? $p['NUMFACTURA'] ?? null;
            $numeros = collect($items)->map($getNumero)->filter()->toArray();
            $syncMap = DB::table('cotizaciones')
                ->whereIn('winperfil_numero', $numeros)
                ->where('winperfil_serie', $serie)
                ->pluck('winperfil_synced_at', 'winperfil_numero');

            $items = collect($items)->map(function ($p) use ($syncMap, $getNumero) {
                $num = $getNumero($p);
                $p['_synced']    = isset($syncMap[$num]);
                $p['_synced_at'] = $syncMap[$num] ?? null;
                return $p;
            })->values()->all();

            return response()->json($items);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    /**
     * Trae un único presupuesto (proxy).
     */
    public function getPresupuesto(Request $request)
    {
        $numero = $request->get('numero');
        $serie  = $request->get('serie', $this->serieDefault);

        try {
            $res = Http::timeout(15)->get("{$this->baseUrl}/erp/presupuesto", [
                'empresa' => $this->empresa,
                'serie'   => $serie,
                'numero'  => $numero,
            ]);

            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    /**
     * Lista pedidos desde Winperfil (proxy).
     */
    public function getPedidos(Request $request)
    {
        $serie = $request->get('serie', $this->serieDefault);
        $desde = $request->get('desde', now()->startOfMonth()->format('Y-m-d'));
        $hasta = $request->get('hasta', now()->format('Y-m-d'));

        try {
            $res = Http::timeout(30)->get("{$this->baseUrl}/erp/pedidos", [
                'empresa'     => $this->empresa,
                'serie'       => $serie,
                'fechaInicio' => $desde,
                'fechaFin'    => $hasta,
                'detalle'     => 'true',
            ]);

            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    /**
     * Lista clientes desde Winperfil (proxy).
     */
    public function getClientes(Request $request)
    {
        $pagina    = (int) $request->get('pagina', 0);
        $porPagina = (int) $request->get('por_pagina', 500);

        try {
            $res = Http::timeout(30)->get("{$this->baseUrl}/erp/clientes", [
                'empresa'        => $this->empresa,
                'pagina'         => $pagina,
                'itemsPorPagina' => $porPagina,
            ]);

            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Sincronización — persiste en la base de datos local
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Sincroniza clientes de Winperfil → tabla clientes.
     */
    public function syncClientes()
    {
        try {
            $res = Http::timeout(30)->get("{$this->baseUrl}/erp/clientes", [
                'empresa'        => $this->empresa,
                'pagina'         => 0,
                'itemsPorPagina' => 2000,
            ]);

            if ($res->failed()) {
                return response()->json(['error' => "Winperfil error {$res->status()}"], 502);
            }

            $body     = $res->json();
            $clientes = $body['clientes'] ?? $body; // clave correcta según docs
            if (!is_array($clientes)) {
                return response()->json(['error' => 'Respuesta inesperada de Winperfil'], 502);
            }

            $creados      = 0;
            $actualizados = 0;
            $omitidos     = 0;

            foreach ($clientes as $c) {
                // Según docs: campos de tabla cliente → nombre, cif (o nif), direccion, etc.
                $cif    = $this->limpiarRut($c['cif'] ?? $c['nif'] ?? $c['CIF'] ?? $c['CIFCLIENTE'] ?? '');
                $nombre = trim($c['nombre'] ?? $c['NOMBRE'] ?? $c['NOMBRECLIENTE'] ?? '');

                if (!$cif && !$nombre) { $omitidos++; continue; }
                if (!$nombre) { $omitidos++; continue; }

                $existing = Cliente::where('identification', $cif)->first();

                if ($existing) {
                    // Solo actualiza si el campo está vacío
                    $updates = [];
                    $email   = $c['email']    ?? $c['EMAIL']    ?? '';
                    $tel     = $c['telefono'] ?? $c['TELEFONO'] ?? '';
                    if (empty($existing->razon_social) && $nombre) $updates['razon_social'] = $nombre;
                    if (empty($existing->email)  && $email) $updates['email'] = $email;
                    if (empty($existing->phone)  && $tel)   $updates['phone'] = $tel;
                    if ($updates) {
                        $existing->update($updates);
                        $actualizados++;
                    } else {
                        $omitidos++;
                    }
                } else {
                    Cliente::create([
                        'razon_social'   => $nombre,
                        'identification' => $cif ?: null,
                        'email'          => $c['email'] ?? $c['EMAIL'] ?? '',
                        'phone'          => $c['telefono'] ?? $c['TELEFONO'] ?? '',
                        'tipo_cliente'   => 'empresa',
                    ]);
                    $creados++;
                }
            }

            return response()->json([
                'ok'          => true,
                'total'       => count($clientes),
                'creados'     => $creados,
                'actualizados'=> $actualizados,
                'omitidos'    => $omitidos,
            ]);

        } catch (\Exception $e) {
            Log::error('WinperfilSync::syncClientes', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sincroniza presupuestos de Winperfil → tabla cotizaciones.
     */
    public function syncPresupuestos(Request $request)
    {
        $serie = $request->get('serie', $this->serieDefault);
        $desde = $request->get('desde', now()->startOfMonth()->format('Y-m-d'));
        $hasta = $request->get('hasta', now()->format('Y-m-d'));

        try {
            $presupuestos = $this->fetchAllPages('presupuestos', $this->paramsConImagen([
                'empresa'     => $this->empresa,
                'serie'       => $serie,
                'fechaInicio' => $desde,
                'fechaFin'    => $hasta,
                'detalle'     => 'true',
            ]));

            // Pre-cargar estados de cotizacion
            $estados   = EstadoCotizacion::all()->keyBy(fn($e) => strtolower($e->nombre));
            $estadoMap = $this->buildEstadoMap($estados);

            $creados      = 0;
            $actualizados = 0;
            $errores      = [];

            foreach ($presupuestos as $pres) {
                try {
                    $result = $this->upsertPresupuesto($pres, $serie, $estadoMap);
                    if ($result === 'created') $creados++;
                    if ($result === 'updated') $actualizados++;
                } catch (\Exception $e) {
                    $num      = $pres['PRESUPUESTO_NUMERO'] ?? $pres['numfactura'] ?? '?';
                    $errores[] = "Presupuesto {$num}: " . $e->getMessage();
                    Log::error("WinperfilSync presupuesto {$num}", ['error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'ok'           => true,
                'total'        => count($presupuestos),
                'creados'      => $creados,
                'actualizados' => $actualizados,
                'errores'      => $errores,
            ]);

        } catch (\Exception $e) {
            Log::error('WinperfilSync::syncPresupuestos', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sincroniza pedidos de Winperfil → tabla winperfil_pedidos.
     */
    public function syncPedidos(Request $request)
    {
        $serie = $request->get('serie', $this->serieDefault);
        $desde = $request->get('desde', now()->startOfMonth()->format('Y-m-d'));
        $hasta = $request->get('hasta', now()->format('Y-m-d'));

        try {
            $pedidos = $this->fetchAllPages('pedidos', [
                'empresa'     => $this->empresa,
                'serie'       => $serie,
                'fechaInicio' => $desde,
                'fechaFin'    => $hasta,
                'detalle'     => 'true',
            ]);

            $creados      = 0;
            $actualizados = 0;

            foreach ($pedidos as $ped) {
                // Según docs: cabecera de pedido usa 'presupuesto' (minúscula)
                $numero = $ped['presupuesto'] ?? $ped['PRESUPUESTO_NUMERO'] ?? $ped['NUMERO_PRESUPUESTO'] ?? null;
                if (!$numero) continue;

                // Buscar cotizacion asociada
                $cotizacion = Cotizacion::where('winperfil_numero', $numero)
                    ->where('winperfil_serie', $serie)
                    ->first();

                $data = [
                    'cotizacion_id'    => $cotizacion?->id,
                    'codigo_enlace'    => $ped['CODIGOENLACE'] ?? null,
                    'codigo_fase'      => $ped['CODIGOFASE'] ?? null,
                    'base'             => isset($ped['BASE']) ? (float) $ped['BASE'] : null,
                    'iva'              => isset($ped['IVA']) ? (float) $ped['IVA'] : null,
                    'estado_general'   => $ped['ESTADOGENERAL'] ?? null,
                    'estado_produccion'=> $ped['ESTADOPRODUCCION'] ?? null,
                    'raw_data'         => json_encode($ped),
                    'updated_at'       => now(),
                ];

                $existing = DB::table('winperfil_pedidos')
                    ->where('numero_presupuesto', $numero)
                    ->where('serie', $serie)
                    ->first();

                if ($existing) {
                    DB::table('winperfil_pedidos')
                        ->where('id', $existing->id)
                        ->update($data);
                    $actualizados++;
                } else {
                    DB::table('winperfil_pedidos')->insert(array_merge($data, [
                        'numero_presupuesto' => $numero,
                        'serie'              => $serie,
                        'created_at'         => now(),
                    ]));
                    $creados++;
                }
            }

            return response()->json([
                'ok'          => true,
                'total'       => count($pedidos),
                'creados'     => $creados,
                'actualizados'=> $actualizados,
            ]);

        } catch (\Exception $e) {
            Log::error('WinperfilSync::syncPedidos', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sincroniza todo: clientes → presupuestos → pedidos.
     */
    public function syncTodo(Request $request)
    {
        $serie = $request->get('serie', $this->serieDefault);
        $desde = $request->get('desde', now()->startOfMonth()->format('Y-m-d'));
        $hasta = $request->get('hasta', now()->format('Y-m-d'));

        $resultados = [];

        // 1. Clientes
        try {
            $resClientes = $this->syncClientesInternal();
            $resultados['clientes'] = $resClientes;
        } catch (\Exception $e) {
            $resultados['clientes'] = ['error' => $e->getMessage()];
        }

        // 2. Presupuestos
        $fakeRequest = new Request(['serie' => $serie, 'desde' => $desde, 'hasta' => $hasta]);
        $resPresup = $this->syncPresupuestos($fakeRequest);
        $resultados['presupuestos'] = $resPresup->getData(true);

        // 3. Pedidos
        $resPedidos = $this->syncPedidos($fakeRequest);
        $resultados['pedidos'] = $resPedidos->getData(true);

        return response()->json([
            'ok'         => true,
            'resultados' => $resultados,
        ]);
    }

    /**
     * Re-sincroniza presupuestos ya sincronizados en la BD.
     *
     * Params:
     *   serie       — serie Winperfil (default: config)
     *   solo_sin_svg — si true, solo procesa los que les falta SVG (default: true)
     *   limite      — cuántos procesar en esta llamada (default: 50, max: 200)
     *   offset      — desde qué posición (para paginación manual)
     */
    public function resyncSincronizados(Request $request)
    {
        // Remover límite de tiempo de PHP (Apache tiene 60s, esto lo anula)
        set_time_limit(0);

        $serie      = $request->get('serie', $this->serieDefault);
        $limite     = min((int) $request->get('limite', 50), 200);
        $offset     = (int) $request->get('offset', 0);
        $soloSinSvg = filter_var($request->get('solo_sin_svg', 'true'), FILTER_VALIDATE_BOOLEAN);

        // Base query: cotizaciones con winperfil sincronizado
        $q = DB::table('cotizaciones as c')
            ->where('c.winperfil_serie', $serie)
            ->whereNotNull('c.winperfil_numero')
            ->select('c.winperfil_numero');

        if ($soloSinSvg) {
            // Solo cotizaciones donde algún detalle le falta el SVG (o no tiene detalles)
            $q->where(function ($w) {
                $w->whereNotExists(function ($sq) {
                    $sq->from('cotizacion_detalles as cd')
                        ->whereColumn('cd.cotizacion_id', 'c.id')
                        ->where('cd.tipo_item', 'winperfil');
                })->orWhereExists(function ($sq) {
                    $sq->from('cotizacion_detalles as cd')
                        ->whereColumn('cd.cotizacion_id', 'c.id')
                        ->where('cd.tipo_item', 'winperfil')
                        ->whereNull('cd.winperfil_grafico');
                });
            });
        }

        $total = $q->count();
        $numerosEnBd = $q->orderBy('c.winperfil_numero')
            ->skip($offset)
            ->take($limite)
            ->pluck('winperfil_numero')
            ->toArray();

        if (empty($numerosEnBd)) {
            return response()->json([
                'ok'          => true,
                'total'       => $total,
                'procesados'  => 0,
                'actualizados'=> 0,
                'errores'     => [],
                'pendientes'  => max(0, $total - $offset),
            ]);
        }

        $estados   = EstadoCotizacion::all()->keyBy(fn($e) => strtolower($e->nombre));
        $estadoMap = $this->buildEstadoMap($estados);

        $actualizados = 0;
        $errores      = [];

        foreach ($numerosEnBd as $numero) {
            try {
                $res = Http::timeout(30)->get("{$this->baseUrl}/erp/presupuesto", $this->paramsConImagen([
                    'empresa' => $this->empresa,
                    'serie'   => $serie,
                    'numero'  => $numero,
                ]));

                if ($res->failed()) {
                    $errores[] = "Presupuesto {$numero}: HTTP {$res->status()}";
                    continue;
                }

                $body   = $res->json();
                $oferta = $body['ofertas'][0] ?? null;
                if (!$oferta) {
                    $oferta = ['cabecera' => $body['cabecera'] ?? $body, 'detalle' => $body['detalle'] ?? []];
                }

                $pres = array_merge(
                    $oferta['cabecera'] ?? $oferta,
                    ['DETALLES' => $oferta['detalle'] ?? []]
                );

                $result = $this->upsertPresupuesto($pres, $serie, $estadoMap);
                if ($result === 'updated') $actualizados++;

            } catch (\Exception $e) {
                $errores[] = "Presupuesto {$numero}: " . $e->getMessage();
            }
        }

        $procesados = count($numerosEnBd);
        $nextOffset = $offset + $procesados;
        $pendientes = max(0, $total - $nextOffset);

        return response()->json([
            'ok'          => true,
            'total'       => $total,
            'procesados'  => $procesados,
            'actualizados'=> $actualizados,
            'errores'     => $errores,
            'pendientes'  => $pendientes,
            'next_offset' => $pendientes > 0 ? $nextOffset : null,
        ]);
    }

    /**
     * Lista cotizaciones sincronizadas de Winperfil con sus pedidos.
     */
    public function cotizacionesSincronizadas(Request $request)
    {
        $q = DB::table('cotizaciones as c')
            ->leftJoin('winperfil_pedidos as wp', function ($j) {
                $j->on('wp.cotizacion_id', '=', 'c.id');
            })
            ->leftJoin('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->leftJoin('estados_cotizacion as ec', 'ec.id', '=', 'c.estado_cotizacion_id')
            ->whereNotNull('c.winperfil_numero')
            ->select(
                'c.id', 'c.fecha', 'c.total', 'c.observaciones',
                'c.winperfil_numero', 'c.winperfil_serie', 'c.winperfil_synced_at',
                'c.adjunto_winperfil',
                'cl.razon_social as cliente_nombre', 'cl.identification as cliente_rut',
                'ec.nombre as estado',
                'wp.codigo_enlace', 'wp.estado_general as pedido_estado',
                'wp.id as pedido_id'
            )
            ->orderByDesc('c.fecha')
            ->orderByDesc('c.winperfil_numero');

        if ($request->filled('serie')) {
            $q->where('c.winperfil_serie', $request->serie);
        }
        if ($request->filled('desde')) {
            $q->where('c.fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $q->where('c.fecha', '<=', $request->hasta);
        }
        if ($request->filled('buscar')) {
            $term = '%' . $request->buscar . '%';
            $q->where(function ($w) use ($term) {
                $w->where('c.observaciones', 'like', $term)
                  ->orWhere('cl.razon_social', 'like', $term);
            });
        }

        return response()->json($q->get());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Helpers privados
    // ══════════════════════════════════════════════════════════════════════════

    private function upsertPresupuesto(array $pres, string $serie, array $estadoMap): string
    {
        // La API puede devolver el número como 'PRESUPUESTO_NUMERO' (mayúsc) o 'numfactura' (minúsc)
        $numero = $pres['PRESUPUESTO_NUMERO'] ?? $pres['numfactura'] ?? $pres['NUMFACTURA'] ?? null;
        if (!$numero) throw new \Exception('Sin PRESUPUESTO_NUMERO/numfactura');

        DB::beginTransaction();
        try {
            // ─── Cliente ────────────────────────────────────────────────────
            $cif = $this->limpiarRut($pres['CIFCLIENTE'] ?? $pres['cifcliente'] ?? '');
            $clienteId = $this->resolverCliente($cif, $pres);

            // ─── Estado ─────────────────────────────────────────────────────
            $aceptado  = strtoupper(trim($pres['ACEPTADO'] ?? $pres['aceptado'] ?? ''));
            $estadoId  = $estadoMap[$aceptado] ?? $estadoMap[''] ?? null;

            // ─── Total ──────────────────────────────────────────────────────
            $base = (float) ($pres['BASE'] ?? $pres['base'] ?? 0);
            $iva  = (float) ($pres['IVA']  ?? $pres['iva']  ?? 19);
            $total = $base * (1 + $iva / 100);

            // ─── Fecha ──────────────────────────────────────────────────────
            $fecha = $this->parseFecha($pres['FECHAFACTURA'] ?? $pres['fechafactura'] ?? null);

            // ─── Observaciones ──────────────────────────────────────────────
            $obs  = "Winperfil Serie {$serie} Nº {$numero}";
            $nombreOferta = $pres['NOMBREOFERTA'] ?? $pres['nombreoferta'] ?? $pres['NOMBRE'] ?? '';
            if (!empty($nombreOferta)) {
                $obs .= ' — ' . $nombreOferta;
            }

            // ─── Upsert cotizacion ───────────────────────────────────────────
            $existing = Cotizacion::where('winperfil_numero', $numero)
                ->where('winperfil_serie', $serie)
                ->first();

            $payload = [
                'cliente_id'          => $clienteId,
                'fecha'               => $fecha,
                'estado_cotizacion_id'=> $estadoId,
                'total'               => $total,
                'observaciones'       => $obs,
                'winperfil_numero'    => $numero,
                'winperfil_serie'     => $serie,
                'winperfil_synced_at' => now(),
            ];

            // Al aceptarse en Winperfil (ACEPTADO='T'), la cotización entra al pipeline
            // de producción en su etapa inicial. No pisa el avance manual: solo lo setea
            // si es nueva o aún no tiene estado_produccion.
            if ($aceptado === 'T' && (!$existing || empty($existing->estado_produccion))) {
                $payload['estado_produccion'] = 'En Espera de Medidas';
            }

            if ($existing) {
                $existing->update($payload);
                $cotizacion = $existing;
                $action = 'updated';
            } else {
                $payload['vendedor_id'] = auth()->id() ?? 1;
                $cotizacion = Cotizacion::create($payload);
                $action = 'created';
            }

            // ─── Detalles ────────────────────────────────────────────────────
            // Eliminar detalles winperfil anteriores y re-insertar
            $cotizacion->detalles()->where('tipo_item', 'winperfil')->delete();

            $detalles = $pres['DETALLES'] ?? $pres['detalle'] ?? $pres['items'] ?? [];
            if (is_array($detalles) && count($detalles)) {
                foreach ($detalles as $det) {
                    // ── Cantidades y precios ─────────────────────────────────
                    $cantidad = (float) ($det['CANTIDAD'] ?? 1);
                    $precio   = (float) ($det['PRECIO']   ?? 0);
                    // SUBTOTAL siempre llega en 0 → calcular manualmente
                    $rawSub   = (float) ($det['SUBTOTAL'] ?? 0);
                    $subtotal = $rawSub > 0 ? $rawSub : ($precio * $cantidad);

                    // ── Dimensiones ──────────────────────────────────────────
                    // ANCHO/ALTO en DETPRE siempre son 0 para ventanas.
                    // Las medidas reales están en ESTRUCTURADESC: "H1=2000 mm V1=1500 mm"
                    $ancho = (float) ($det['ANCHO'] ?? 0);
                    $alto  = (float) ($det['ALTO']  ?? 0);
                    $estructura = $det['ESTRUCTURADESC'] ?? '';
                    if ($estructura) {
                        // H1 = horizontal = ancho (width)
                        if (preg_match('/H1=(\d+)\s*mm/i', $estructura, $hm)) {
                            $ancho = (float) $hm[1];
                        }
                        // V1 = vertical = alto (height)
                        if (preg_match('/V1=(\d+)\s*mm/i', $estructura, $vm)) {
                            $alto = (float) $vm[1];
                        }
                    }

                    // ── Descripción ──────────────────────────────────────────
                    // DESCRIPCION_TXT no existe en esta versión de Winperfil.
                    // ARTICULO = etiqueta del usuario (ej. "P. Sala estar V1")
                    // DESCSEGUNMODELO = nombre del modelo (ej. "ventana corredera 2 hojas")
                    // fixEncoding corrige ñ, á, etc. que llegan en cp1252
                    $articulo   = $this->fixEncoding(trim($det['ARTICULO']       ?? ''));
                    $descModelo = $this->fixEncoding(trim($det['DESCSEGUNMODELO'] ?? ''));

                    if ($articulo && $descModelo && strtolower($articulo) !== strtolower($descModelo)) {
                        $descripcion = $articulo . ' — ' . $descModelo;
                    } elseif ($articulo) {
                        $descripcion = $articulo;
                    } elseif ($descModelo) {
                        $descripcion = $descModelo;
                    } else {
                        // Último recurso: intentar limpiar el RTF de DESCRIPCION
                        $descripcion = $this->stripRtf($det['DESCRIPCION'] ?? '');
                        if (!$descripcion) $descripcion = 'Ventana Winperfil';
                    }

                    // ── SVG (imagen=true requerido) ──────────────────────────
                    $grafico = $det['GRAFICO_SVG_BASE64'] ?? null;

                    CotizacionDetalle::create([
                        'cotizacion_id'    => $cotizacion->id,
                        'tipo_item'        => 'winperfil',
                        'descripcion'      => $descripcion ?: 'Sin descripción',
                        'cantidad'         => max($cantidad, 1),
                        'precio_unitario'  => $precio,
                        'total'            => $subtotal,
                        'ancho_mm'         => $ancho > 0 ? $ancho : null,
                        'alto_mm'          => $alto  > 0 ? $alto  : null,
                        'winperfil_grafico'=> $grafico,
                    ]);
                }
            }

            DB::commit();
            return $action;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Descarga TODAS las páginas de un endpoint Winperfil paginado.
     * Devuelve los items ya aplanados ({cabecera, detalle} → {...cabecera, DETALLES:[...]}).
     */
    /**
     * Agrega imagen=true a los params para obtener GRAFICO_SVG_BASE64.
     */
    private function paramsConImagen(array $params): array
    {
        return array_merge($params, ['imagen' => 'true']);
    }

    /**
     * Descarga TODAS las páginas de un endpoint Winperfil paginado.
     * Maneja las distintas claves de respuesta: datos (presupuestos), pedidos, clientes.
     */
    private function fetchAllPages(string $endpoint, array $params, int $porPagina = 500): array
    {
        // Mapa de endpoint → clave del array en la respuesta
        $claveRespuesta = [
            'presupuestos' => 'datos',
            'pedidos'      => 'pedidos',
            'clientes'     => 'clientes',
        ];
        $clave = $claveRespuesta[$endpoint] ?? 'datos';

        $todos  = [];
        $pagina = 0;

        do {
            $res = Http::timeout(60)->get("{$this->baseUrl}/erp/{$endpoint}", array_merge($params, [
                'pagina'         => $pagina,
                'itemsPorPagina' => $porPagina,
            ]));

            if ($res->failed()) {
                throw new \Exception("Winperfil error {$res->status()} en página {$pagina}");
            }

            $body  = $res->json();
            $datos = $body[$clave] ?? $body;

            if (!is_array($datos) || count($datos) === 0) break;

            foreach ($datos as $d) {
                $todos[] = array_merge(
                    $d['cabecera'] ?? $d,
                    ['DETALLES' => $d['detalle'] ?? []]
                );
            }

            if (count($datos) < $porPagina) break;
            $pagina++;

        } while (true);

        return $todos;
    }

    private function resolverCliente(string $cif, array $pres): int
    {
        if ($cif) {
            $cliente = Cliente::where('identification', $cif)->first();
            if ($cliente) return $cliente->id;
        }

        $nombre = $this->fixEncoding(trim($pres['NOMBRECLIENTE'] ?? $pres['nombrecliente'] ?? $pres['nombre'] ?? ''));
        if (!$nombre && !$cif) {
            // Fallback: primer cliente disponible (no ideal, pero no bloquea la sync)
            return Cliente::first()?->id ?? 1;
        }

        // Crear cliente nuevo desde los datos del presupuesto
        $cliente = Cliente::create([
            'razon_social'   => $nombre ?: "Cliente Winperfil ({$cif})",
            'identification' => $cif ?: null,
            'email'          => $pres['EMAILCLIENTE'] ?? $pres['emailcliente'] ?? '',
            'tipo_cliente'   => 'empresa',
        ]);

        return $cliente->id;
    }

    private function buildEstadoMap($estados): array
    {
        // Mapa de campo ACEPTADO de Winperfil → estado local:
        //   ''  = no confirmado aún      → Evaluación
        //   'T' = aceptado/confirmado    → Aceptado
        //   'C' = cerrado/facturado      → Facturado/Cerrado
        //   'F' = no aceptado en Winperf → Evaluación (NO rechazado:
        //         en Winperfil 'F' puede ser simplemente un presupuesto
        //         pendiente de confirmación, no un rechazo definitivo)
        $map = ['' => null, 'T' => null, 'C' => null, 'F' => null];

        foreach ($estados as $estado) {
            $n = strtolower($estado->nombre);
            if (str_contains($n, 'valuaci') || str_contains($n, 'pendient')) {
                $map[''] = $estado->id;
            } elseif (str_contains($n, 'aprobad') || str_contains($n, 'aceptad')) {
                $map['T'] = $estado->id;
            } elseif (str_contains($n, 'facturad') || str_contains($n, 'cerrad')) {
                $map['C'] = $estado->id;
            }
            // 'F' (no aceptado) → se asigna después como Evaluación (igual que '')
        }

        // 'F' → mismo estado que '' (Evaluación), no Rechazado
        $map['F'] = $map[''];

        // Si no hay coincidencia exacta, usar el primero disponible como fallback
        $fallback = $estados->first()?->id;
        foreach ($map as $k => $v) {
            if ($v === null) $map[$k] = $fallback;
        }

        return $map;
    }

    /**
     * Elimina formato RTF de un string, retornando texto plano.
     * Funciona incluso si el header {\rtf1 fue cortado por la API.
     */
    private function stripRtf(?string $rtf): string
    {
        if (!$rtf) return '';

        $text = $rtf;

        // 1. Eliminar grupos RTF completos: {\*\command ...} y {\command ...}
        //    (font tables, color tables, etc.)
        $prev = '';
        while ($prev !== $text) {
            $prev = $text;
            $text = preg_replace('/\{[^{}]*\}/', '', $text);
        }

        // 2. Reemplazar escapes hexadecimales \'XX → vacío (o carácter real si quieres)
        $text = preg_replace("/\\\\'[0-9a-fA-F]{2}/", '', $text);
        // También la variante ya parcialmente procesada: 'XX (sin backslash)
        $text = preg_replace("/'[0-9a-fA-F]{2}\b/", '', $text);

        // 3. Reemplazar saltos de párrafo por espacio
        $text = preg_replace('/\\\\(par|line|tab)\b\s?/', ' ', $text);

        // 4. Eliminar palabras de control RTF: \word o \word123
        $text = preg_replace('/\\\\[a-zA-Z]+\-?[0-9]* ?/', '', $text);

        // 5. Eliminar llaves y backslashes restantes
        $text = str_replace(['{', '}', '\\'], '', $text);

        // 6. Heurística Winperfil: la tabla de fuentes termina en ";;",
        //    el texto real empieza después del PRIMER ;; (strpos, no strrpos)
        if (str_contains($text, ';;')) {
            $candidate = trim(substr($text, strpos($text, ';;') + 2));
            // Solo usar si el candidato tiene contenido útil (>5 chars)
            if (strlen($candidate) > 5) {
                $text = $candidate;
            }
        }

        // 7. Los bullets Winperfil quedan como 'B7 o B7 después de strip — reemplazar por " · "
        $text = preg_replace("/'?B7\b/i", ' · ', $text);

        // 8. Limpiar asteriscos y caracteres de control remanentes
        $text = preg_replace('/[*]+/', ' ', $text);

        $result = trim(preg_replace('/\s+/', ' ', $text));

        // 9. Si después de todo quedó texto muy corto, devolver vacío para usar fallback
        return strlen($result) > 4 ? $result : '';
    }

    /**
     * Corrige strings con encoding cp1252/Latin-1 mal interpretados como UTF-8.
     * Winperfil puede enviar bytes cp1252 (como ñ=0xF1) en respuestas JSON.
     */
    private function fixEncoding(?string $str): string
    {
        if (!$str) return '';
        // Si ya es UTF-8 válido, no tocar
        if (mb_check_encoding($str, 'UTF-8')) return $str;
        // Convertir desde Windows-1252 a UTF-8
        return mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
    }

    /**
     * Limpia un RUT/CIF chileno o europeo.
     */
    private function limpiarRut(string $rut): string
    {
        return preg_replace('/[^0-9kK]/', '', $rut);
    }

    /**
     * Parsea una fecha de Winperfil (varios formatos posibles).
     */
    private function parseFecha(?string $fecha): string
    {
        if (!$fecha) return now()->format('Y-m-d');

        // Formato dd/mm/yyyy o dd-mm-yyyy
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $fecha, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        // Formato yyyy-mm-dd
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha)) {
            return substr($fecha, 0, 10);
        }

        $ts = strtotime($fecha);
        return $ts ? date('Y-m-d', $ts) : now()->format('Y-m-d');
    }

    /**
     * Versión interna de syncClientes (para llamar desde syncTodo).
     */
    private function syncClientesInternal(): array
    {
        $res = $this->syncClientes();
        return $res->getData(true);
    }
}
