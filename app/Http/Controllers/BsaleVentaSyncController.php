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
        5  => 'cheque',
        6  => 'tarjeta_debito',
        8  => 'transferencia',
        10 => 'tarjeta_credito',  // webpay
        13 => 'tarjeta_credito',  // mercadopago
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

            foreach ($res->json()['items'] ?? [] as $pago) {
                $typeId = (int) ($pago['payment_type']['id'] ?? 0);
                if (!$typeId) continue;

                $result['payment_type_id'] = $typeId;
                $result['forma_pago']      = self::FORMA_PAGO_MAP[$typeId] ?? null;
                $result['tarjeta']         = in_array($typeId, [2, 6, 10, 13]);

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
}
