<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BsaleVentaSyncController extends Controller
{
    private string $token;
    private string $baseUrl;

    private const TIPOS_VENTA = [1, 3, 4, 5, 6];
    private const TIPOS_NC    = [2];

    // paymentTypeId de Bsale → forma_pago normalizada para boleta_resumenes
    private const FORMA_PAGO_MAP = [
        1  => 'efectivo',
        2  => 'tarjeta_credito',
        3  => 'nota_credito',
        4  => 'credito',
        5  => 'cheque',
        6  => 'tarjeta_debito',
        7  => 'transferencia',    // abono/transferencia interna
        8  => 'transferencia',
        9  => 'tarjeta_credito',
        10 => 'tarjeta_credito',  // webpay
        11 => 'tarjeta_credito',
        12 => 'tarjeta_credito',
        13 => 'tarjeta_credito',  // mercadopago
        14 => 'efectivo',
        15 => 'efectivo',
    ];

    public function __construct()
    {
        $this->token   = config('services.bsale.access_token', '');
        $this->baseUrl = config('services.bsale.base_url', 'https://api.bsale.cl/v1/');
    }

    // ── POST /api/ventas/sincronizar ──────────────────────────────────────────

    public function sincronizar(Request $request)
    {
        set_time_limit(0);

        $años = $request->get('años', [now()->year]);
        if (!is_array($años)) $años = [$años];

        $nuevos   = 0;
        $omitidos = 0;
        $errores  = 0;

        // Cache de clientes Bsale ya resueltos (bsale_client_id → datos)
        $clienteCache = [];

        foreach ($años as $anio) {
            $offset = 0;
            $limit  = 50;

            do {
                $res = Http::timeout(20)
                    ->withHeaders(['access_token' => $this->token])
                    ->get($this->baseUrl . 'documents.json', [
                        'emissiondaterange' => $this->rangoAnio((int) $anio),
                        'limit'             => $limit,
                        'offset'            => $offset,
                    ]);

                if ($res->failed()) {
                    Log::error('BsaleVentaSync: error API', ['status' => $res->status(), 'anio' => $anio]);
                    break;
                }

                $items = $res->json()['items'] ?? [];

                foreach ($items as $doc) {
                    $tipo = (int) ($doc['document_type']['id'] ?? 0);
                    if (!in_array($tipo, array_merge(self::TIPOS_VENTA, self::TIPOS_NC))) continue;

                    try {
                        $resultado = $this->procesarDocumento($doc, $clienteCache);
                        if ($resultado === 'nuevo')   $nuevos++;
                        if ($resultado === 'omitido') $omitidos++;
                    } catch (\Throwable $e) {
                        $errores++;
                        Log::error('BsaleVentaSync: error procesando doc', [
                            'bsale_id' => $doc['id'] ?? null,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }

                $offset += $limit;
            } while (count($items) === $limit);
        }

        return response()->json([
            'success'  => true,
            'nuevos'   => $nuevos,
            'omitidos' => $omitidos,
            'errores'  => $errores,
        ]);
    }

    // ── Procesar un documento de Bsale ────────────────────────────────────────

    private function procesarDocumento(array $doc, array &$clienteCache): string
    {
        $bsaleId = (string) $doc['id'];

        // Si ya existe en DB → actualizar url_pdf_bsale si falta, luego omitir
        $existente = DB::table('documentos_facturacion')
            ->where('id_documento_bsale', $bsaleId)
            ->select('id', 'url_pdf_bsale')
            ->first();

        if ($existente) {
            if (!$existente->url_pdf_bsale && !empty($doc['urlPdf'])) {
                DB::table('documentos_facturacion')
                    ->where('id', $existente->id)
                    ->update(['url_pdf_bsale' => $doc['urlPdf'], 'updated_at' => now()]);
            }
            return 'omitido';
        }

        $tipo     = (int) ($doc['document_type']['id'] ?? 0);
        $esNC     = in_array($tipo, self::TIPOS_NC);
        $bruto    = (float) ($doc['totalAmount'] ?? 0) * ($esNC ? -1 : 1);
        $neto     = isset($doc['netAmount'])
            ? (int) ((float) $doc['netAmount'] * ($esNC ? -1 : 1))
            : (int) round($bruto / 1.19);

        $fechaEmision = isset($doc['emissionDate'])
            ? Carbon::createFromTimestamp($doc['emissionDate'])->toDateString()
            : null;

        // Resolver cliente
        [$clienteId, $clienteRut, $clienteNombre] =
            $this->resolverCliente($doc, $clienteCache);

        $pago = $this->obtenerInfoPago((int) $bsaleId);

        DB::table('documentos_facturacion')->insert([
            'cotizacion_id'              => null,
            'cliente_id'                 => $clienteId,
            'bsale_cliente_rut'          => $clienteRut,
            'bsale_cliente_nombre'       => $clienteNombre,
            'tipo'                       => 'total',
            'porcentaje'                 => 100.00,
            'monto'                      => (int) abs($bruto),
            'neto'                       => (int) abs($neto),
            'estado'                     => 'emitido',
            'id_documento_bsale'         => $bsaleId,
            'numero_documento_bsale'     => (string) ($doc['number'] ?? ''),
            'url_pdf_bsale'              => $doc['urlPdf'] ?? null,
            'fecha_emision'              => $fechaEmision,
            'tipo_documento_bsale_id'    => $tipo,
            'nro_comprobante_transbank'  => $pago['comprobante'],
            'pagado_con_tarjeta'         => $pago['tarjeta'] ? 1 : 0,
            'payment_type_id'            => $pago['payment_type_id'],
            'forma_pago'                 => $pago['forma_pago'],
            'nota'                       => null,
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);

        return 'nuevo';
    }

    // ── POST /api/ventas/backfill-forma-pago ─────────────────────────────────
    // Rellena payment_type_id y forma_pago para boletas 2026 ya sincronizadas.
    // Llamar en lotes (limit=50) hasta que pendientes=0.

    public function backfillFormaPago(Request $request)
    {
        set_time_limit(0);
        $limit = (int) $request->get('limit', 50);

        $docs = DB::table('documentos_facturacion')
            ->whereNotNull('id_documento_bsale')
            ->whereIn('tipo_documento_bsale_id', [1])
            ->whereNull('forma_pago')
            ->where('fecha_emision', '>=', '2026-01-01')
            ->select('id', 'id_documento_bsale')
            ->limit($limit)
            ->get();

        $actualizados = 0;

        foreach ($docs as $doc) {
            $pago = $this->obtenerInfoPago((int) $doc->id_documento_bsale);

            DB::table('documentos_facturacion')
                ->where('id', $doc->id)
                ->update([
                    'payment_type_id' => $pago['payment_type_id'],
                    'forma_pago'      => $pago['forma_pago'],
                    'updated_at'      => now(),
                ]);

            $actualizados++;
        }

        $pendientes = DB::table('documentos_facturacion')
            ->whereNotNull('id_documento_bsale')
            ->whereIn('tipo_documento_bsale_id', [1])
            ->whereNull('forma_pago')
            ->where('fecha_emision', '>=', '2026-01-01')
            ->count();

        return response()->json([
            'success'      => true,
            'revisados'    => $docs->count(),
            'actualizados' => $actualizados,
            'pendientes'   => $pendientes,
        ]);
    }

    // ── POST /api/ventas/backfill-comprobantes ────────────────────────────────
    // Rellena nro_comprobante_transbank y pagado_con_tarjeta para docs ya sincronizados.

    public function backfillComprobantes(Request $request)
    {
        set_time_limit(0);

        $limit = (int) $request->get('limit', 150);

        // Procesa docs donde aún no sabemos si fue pagado con tarjeta
        $docs = DB::table('documentos_facturacion')
            ->whereNotNull('id_documento_bsale')
            ->whereNull('pagado_con_tarjeta')
            ->select('id', 'id_documento_bsale')
            ->limit($limit)
            ->get();

        $actualizados = 0;

        foreach ($docs as $doc) {
            ['tarjeta' => $tarjeta, 'comprobante' => $comprobante] =
                $this->obtenerInfoTarjeta((int) $doc->id_documento_bsale);

            if ($tarjeta) {
                DB::table('documentos_facturacion')
                    ->where('id', $doc->id)
                    ->update([
                        'pagado_con_tarjeta'        => 1,
                        'nro_comprobante_transbank'  => $comprobante, // puede ser null si no digitaron
                        'updated_at'                => now(),
                    ]);
                $actualizados++;
            } else {
                // Confirmado sin tarjeta → marcar para no reprocesar
                DB::table('documentos_facturacion')
                    ->where('id', $doc->id)
                    ->update(['pagado_con_tarjeta' => 0, 'updated_at' => now()]);
            }
        }

        $pendientes = DB::table('documentos_facturacion')
            ->whereNotNull('id_documento_bsale')
            ->whereNull('pagado_con_tarjeta')
            ->count();

        return response()->json([
            'success'      => true,
            'revisados'    => $docs->count(),
            'actualizados' => $actualizados,
            'pendientes'   => $pendientes,
        ]);
    }

    // ── Consultar Bsale payments: forma de pago + comprobante ────────────────

    private function obtenerInfoPago(int $bsaleId): array
    {
        $result = ['tarjeta' => false, 'comprobante' => null, 'payment_type_id' => null, 'forma_pago' => null];

        try {
            $res = Http::timeout(10)
                ->withHeaders(['access_token' => $this->token])
                ->get($this->baseUrl . 'payments.json', [
                    'documentid' => $bsaleId,
                    'expand'     => 'attributes',
                ]);

            if (!$res->ok()) return $result;

            $items = $res->json()['items'] ?? [];

            // Sin datos de pago en Bsale → sentinel para no reintentar en backfill
            if (empty($items)) {
                $result['forma_pago'] = 'sin_informacion';
                return $result;
            }

            foreach ($items as $pago) {
                $typeId = (int) ($pago['payment_type']['id'] ?? 0);
                if (!$typeId) continue;

                $result['payment_type_id'] = $typeId;
                // Tipos no mapeados van a 'otros' para no perderse
                $result['forma_pago']      = self::FORMA_PAGO_MAP[$typeId] ?? 'otros';
                $result['tarjeta']         = in_array($typeId, [2, 6, 9, 10, 11, 12, 13]);

                if ($result['tarjeta']) {
                    foreach ($pago['attributes'] ?? [] as $attr) {
                        if (($attr['name'] ?? '') === 'Nº Comprobante' && !empty($attr['value'])) {
                            $result['comprobante'] = (string) ltrim((string) $attr['value'], '0') ?: null;
                            break;
                        }
                    }
                }

                break; // usar el primer medio de pago
            }
        } catch (\Throwable) {}

        return $result;
    }

    private function obtenerInfoTarjeta(int $bsaleId): array
    {
        $p = $this->obtenerInfoPago($bsaleId);
        return ['tarjeta' => $p['tarjeta'], 'comprobante' => $p['comprobante']];
    }

    // ── Resolver cliente: cache + lookup local por RUT ────────────────────────

    private function resolverCliente(array $doc, array &$cache): array
    {
        $bsaleClientId = $doc['client']['id'] ?? null;
        $clienteRut    = null;
        $clienteNombre = 'Consumidor Final';

        if ($bsaleClientId && isset($doc['client']['href'])) {
            if (!isset($cache[$bsaleClientId])) {
                $cr = Http::timeout(10)
                    ->withHeaders(['access_token' => $this->token])
                    ->get($doc['client']['href']);

                if ($cr->ok()) {
                    $cd = $cr->json();
                    $cache[$bsaleClientId] = [
                        'rut'    => $cd['code'] ?? null,
                        'nombre' => $cd['company']
                            ?? trim(($cd['firstName'] ?? '') . ' ' . ($cd['lastName'] ?? ''))
                            ?: 'Consumidor Final',
                    ];
                } else {
                    $cache[$bsaleClientId] = ['rut' => null, 'nombre' => 'Consumidor Final'];
                }
            }

            $clienteRut    = $cache[$bsaleClientId]['rut'];
            $clienteNombre = $cache[$bsaleClientId]['nombre'];
        }

        // Buscar cliente local por RUT
        $localClienteId = null;
        if ($clienteRut) {
            $localClienteId = DB::table('clientes')
                ->where('identification', $clienteRut)
                ->value('id');
        }

        return [$localClienteId, $clienteRut, $clienteNombre];
    }

    // ── Helper: rango Unix timestamp del año completo ─────────────────────────

    private function rangoAnio(int $anio): string
    {
        $inicio = Carbon::create($anio, 1, 1)->startOfYear()->timestamp;
        $fin    = Carbon::create($anio, 12, 31)->endOfYear()->timestamp;
        return "[$inicio,$fin]";
    }

    // ── POST /api/ventas/importar-lineas ──────────────────────────────────────
    // Importa las líneas (detalle) de documentos Bsale hacia documento_items,
    // para tener historial de productos vendidos por cliente. Llamar en lotes.
    public function importarLineas(Request $request)
    {
        set_time_limit(0);
        $limit = (int) $request->get('limit', 40);

        $docs = DB::table('documentos_facturacion as df')
            ->whereNotNull('df.id_documento_bsale')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('documento_items as di')
                  ->whereColumn('di.documento_facturacion_id', 'df.id');
            })
            ->orderByDesc('df.fecha_emision')
            ->limit($limit)
            ->get(['df.id', 'df.id_documento_bsale']);

        $importados = 0;

        foreach ($docs as $doc) {
            try {
                $res = Http::timeout(15)
                    ->withHeaders(['access_token' => $this->token])
                    ->get($this->baseUrl . "documents/{$doc->id_documento_bsale}/details.json");

                if (!$res->ok()) {
                    continue; // se reintenta en el próximo lote
                }

                $items = $res->json()['items'] ?? [];
                $rows  = [];

                foreach ($items as $it) {
                    $nombre = trim((string) ($it['comment'] ?? ''))
                        ?: trim((string) ($it['variant']['description'] ?? ''))
                        ?: 'Producto';

                    $rows[] = [
                        'documento_facturacion_id' => $doc->id,
                        'producto_id'    => null,
                        'nombre'         => mb_substr($nombre, 0, 255),
                        'cantidad'       => (float) ($it['quantity'] ?? 0),
                        'precio_unitario'=> (int) round((float) ($it['netUnitValue'] ?? 0)),
                        'descuento'      => 0,
                        'total_neto'     => (int) round((float) ($it['netAmount'] ?? 0)),
                        'es_vidrio'      => 0,
                        'pulido'         => 0,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }

                if (empty($rows)) {
                    // Sin detalle → placeholder para no reprocesar infinito
                    $rows[] = [
                        'documento_facturacion_id' => $doc->id,
                        'producto_id' => null, 'nombre' => '(sin detalle)',
                        'cantidad' => 0, 'precio_unitario' => 0, 'descuento' => 0, 'total_neto' => 0,
                        'es_vidrio' => 0, 'pulido' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ];
                }

                DB::table('documento_items')->insert($rows);
                $importados++;
            } catch (\Throwable $e) {
                Log::warning('importarLineas', ['doc' => $doc->id, 'error' => $e->getMessage()]);
            }
        }

        $pendientes = DB::table('documentos_facturacion as df')
            ->whereNotNull('df.id_documento_bsale')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('documento_items as di')
                  ->whereColumn('di.documento_facturacion_id', 'df.id');
            })->count();

        return response()->json(['importados' => $importados, 'pendientes' => $pendientes]);
    }

    // ── GET /api/ventas/historial-productos?cliente=&q= ───────────────────────
    // Historial de productos vendidos (líneas), filtrable por cliente y producto.
    public function historialProductos(Request $request)
    {
        $cliente = trim((string) $request->get('cliente', ''));
        $q       = trim((string) $request->get('q', ''));

        $query = DB::table('documento_items as di')
            ->join('documentos_facturacion as df', 'df.id', '=', 'di.documento_facturacion_id')
            ->leftJoin('clientes as c', 'c.id', '=', 'df.cliente_id')
            ->where('di.nombre', '!=', '(sin detalle)')
            ->whereNotNull('df.fecha_emision')
            ->when($q !== '', function ($w) use ($q) {
                foreach (array_filter(explode(' ', $q)) as $pal) {
                    $w->where('di.nombre', 'like', "%{$pal}%");
                }
            })
            ->when($cliente !== '', function ($w) use ($cliente) {
                $w->where(function ($x) use ($cliente) {
                    $x->where('df.bsale_cliente_nombre', 'like', "%{$cliente}%")
                      ->orWhere('df.bsale_cliente_rut', 'like', "%{$cliente}%")
                      ->orWhere('c.razon_social', 'like', "%{$cliente}%")
                      ->orWhere('c.first_name', 'like', "%{$cliente}%")
                      ->orWhere('c.last_name', 'like', "%{$cliente}%");
                });
            })
            ->selectRaw("di.nombre as producto, di.cantidad, di.precio_unitario, di.total_neto,
                df.fecha_emision, df.numero_documento_bsale, df.tipo_documento_bsale_id,
                COALESCE(c.razon_social, NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), df.bsale_cliente_nombre) as cliente")
            ->orderByDesc('df.fecha_emision')
            ->limit(500);

        return response()->json($query->get());
    }
}
