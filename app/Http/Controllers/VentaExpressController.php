<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\DocumentoFacturacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VentaExpressController extends Controller
{
    private string $baseUrl;
    private ?string $accessToken;

    // forma_pago (string) → paymentTypeId de Bsale
    private array $paymentTypes = [
        'efectivo'        => 1,
        'tarjeta_credito' => 2,
        'nota_credito'    => 3,
        'credito'         => 4,
        'cheque'          => 5,
        'tarjeta_debito'  => 6,
        'abono'           => 7,
        'transferencia'   => 8,
        'webpay'          => 10,
        'mercadopago'     => 13,
    ];

    public function __construct()
    {
        $this->baseUrl     = config('services.bsale.base_url', 'https://api.bsale.cl/v1/');
        $this->accessToken = config('services.bsale.access_token');
    }

    /**
     * GET /api/venta-express/productos?q=  — busca productos de la app con precio de venta.
     */
    public function buscarProductos(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // Búsqueda flexible: cada palabra debe aparecer en el nombre o el color,
        // en cualquier orden y parcial (ej: "tub 80 blanco" → "Tubular 80 mm" + color "Blanco").
        $palabras = array_filter(explode(' ', preg_replace('/\s+/', ' ', $q)));

        $rows = DB::table('lista_precios as lp')
            ->join('productos as p', 'p.id', '=', 'lp.producto_id')
            ->leftJoin('colores as c', 'c.id', '=', 'lp.color_id')
            ->where('lp.activo', 1)
            ->where('lp.precio_venta', '>', 0)
            ->where(function ($outer) use ($palabras) {
                foreach ($palabras as $pal) {
                    $outer->where(function ($w) use ($pal) {
                        $w->where('p.nombre', 'like', "%$pal%")
                          ->orWhere('c.nombre', 'like', "%$pal%");
                    });
                }
            })
            ->orderBy('p.nombre')
            ->limit(30)
            ->get(['lp.id', 'p.nombre as producto', 'c.nombre as color', 'lp.precio_venta', 'p.tipo_producto_id']);

        // tipos 1, 2 y 7 = cristales/vidrios → se venden por m²
        $tiposVidrio = [1, 2, 7];

        $items = $rows->map(fn ($r) => [
            'id'           => $r->id,
            'nombre'       => trim($r->producto . ($r->color ? " - {$r->color}" : '')),
            'precio_venta' => (float) $r->precio_venta,
            'es_vidrio'    => in_array((int) $r->tipo_producto_id, $tiposVidrio, true),
        ]);

        return response()->json($items);
    }

    /**
     * POST /api/venta-express/emitir — emite boleta/factura en Bsale y registra local.
     * Body: { tipo:'boleta'|'factura', cliente_id?, forma_pago, items:[{nombre,cantidad,precio,descuento?}] }
     * precio = neto unitario.
     */
    public function emitir(Request $request)
    {
        $data = $request->validate([
            'tipo'              => 'required|in:boleta,factura',
            'cliente_id'        => 'nullable|integer|exists:clientes,id',
            'forma_pago'        => 'required|string',
            'items'             => 'required|array|min:1',
            'items.*.nombre'    => 'required|string',
            'items.*.cantidad'  => 'required|numeric|min:0.0001',
            'items.*.precio'    => 'required|numeric|min:0',
            'items.*.descuento' => 'nullable|numeric|min:0|max:100',
        ]);

        if (!$this->accessToken) {
            return response()->json(['success' => false, 'error' => 'Token de Bsale no configurado en el servidor.'], 500);
        }

        $esBoleta        = $data['tipo'] === 'boleta';
        $documentTypeId  = $esBoleta ? 1 : 5; // 1=Boleta, 5=Factura Electrónica
        $clienteBsaleId  = 1;                  // Consumidor Final por defecto
        $clienteNombre   = 'Consumidor Final';

        $cliente = !empty($data['cliente_id']) ? Cliente::find($data['cliente_id']) : null;

        if (!$esBoleta) {
            // Factura: cliente obligatorio y sincronizado con Bsale
            if (!$cliente || !$cliente->bsale_id) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Para factura debes seleccionar un cliente con RUT sincronizado en Bsale.',
                ], 422);
            }
            $clienteBsaleId = (int) $cliente->bsale_id;
            $clienteNombre  = $this->nombreCliente($cliente);
        } elseif ($cliente && $cliente->bsale_id) {
            // Boleta con cliente conocido (opcional)
            $clienteBsaleId = (int) $cliente->bsale_id;
            $clienteNombre  = $this->nombreCliente($cliente);
        }

        // Construir detalles + total
        $details   = [];
        $totalNeto = 0;
        foreach ($data['items'] as $it) {
            $neto = (float) $it['precio'];
            $cant = (float) $it['cantidad'];
            $desc = (float) ($it['descuento'] ?? 0);

            $details[] = [
                'netUnitValue' => round($neto, 4),
                'quantity'     => $cant,
                'taxId'        => '[1]',
                'comment'      => mb_substr(trim($it['nombre']), 0, 100),
                'discount'     => $desc,
            ];

            $totalNeto += $neto * $cant * (1 - $desc / 100);
        }
        $totalBruto = (int) round($totalNeto * 1.19);

        $payload = [
            'documentTypeId' => $documentTypeId,
            'officeId'       => 1,
            'emissionDate'   => time(),
            'expirationDate' => time(),
            'declareSii'     => 1,
            'clientId'       => $clienteBsaleId,
            'details'        => $details,
            'payments'       => [[
                'paymentTypeId' => $this->paymentTypes[$data['forma_pago']] ?? 1,
                'amount'        => $totalBruto,
                'recordDate'    => time(),
            ]],
        ];

        // Emitir en Bsale
        try {
            $res = Http::withHeaders([
                'access_token' => $this->accessToken,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->timeout(30)->post($this->baseUrl . 'documents.json', $payload);
        } catch (\Throwable $e) {
            Log::error('VentaExpress: excepción Bsale', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'No se pudo conectar con Bsale: ' . $e->getMessage()], 500);
        }

        if (!$res->successful()) {
            Log::error('VentaExpress: Bsale rechazó', ['status' => $res->status(), 'body' => $res->body()]);
            $err = $res->json()['error'] ?? $res->body();
            return response()->json(['success' => false, 'error' => 'Bsale rechazó el documento: ' . $err], 502);
        }

        $bsale = $res->json();

        // Registrar local (aparece de inmediato en Registro de Ventas / Boletas / CxC)
        $doc = DocumentoFacturacion::create([
            'cotizacion_id'           => null,
            'tipo'                    => 'total',
            'porcentaje'              => 100,
            'monto'                   => $totalBruto,
            'estado'                  => 'emitido',
            'tipo_documento_bsale_id' => $documentTypeId,
            'forma_pago'              => $data['forma_pago'],
            'bsale_cliente_nombre'    => $clienteNombre,
            'id_documento_bsale'      => $bsale['id'] ?? null,
            'numero_documento_bsale'  => $bsale['number'] ?? null,
            'url_pdf_bsale'           => $bsale['urlPdf'] ?? null,
            'fecha_emision'           => now()->toDateString(),
        ]);

        // Si es boleta, recalcular el resumen del mes para que aparezca al instante en el módulo Boletas
        if ($esBoleta) {
            try {
                Artisan::call('boletas:recalcular-resumenes', ['--periodo' => now()->format('Y-m')]);
            } catch (\Throwable $e) {
                Log::warning('VentaExpress: no se pudo recalcular resumen de boletas', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success'   => true,
            'documento' => [
                'numero' => $bsale['number'] ?? null,
                'pdf'    => $bsale['urlPdf'] ?? null,
                'total'  => $totalBruto,
                'tipo'   => $esBoleta ? 'Boleta' : 'Factura',
                'cliente'=> $clienteNombre,
            ],
            'doc_id' => $doc->id,
        ]);
    }

    private function nombreCliente(Cliente $c): string
    {
        return $c->razon_social
            ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''))
            ?: 'Cliente';
    }
}
