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
            ->get(['lp.id', 'p.id as producto_id', 'p.nombre as producto', 'c.nombre as color', 'lp.precio_venta', 'p.tipo_producto_id']);

        // tipos 1, 2 y 7 = cristales/vidrios → se venden por m²
        $tiposVidrio = [1, 2, 7];

        $items = $rows->map(fn ($r) => [
            'id'           => $r->id, // lista_precio id (key único)
            'producto_id'  => $r->producto_id ?? null,
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
            'tipo'               => 'required|in:boleta,factura',
            'cliente_id'         => 'nullable|integer|exists:clientes,id',
            'observaciones'      => 'nullable|string',
            // Pagos (uno o varios, ej. efectivo + transbank)
            'pagos'              => 'required|array|min:1',
            'pagos.*.forma_pago' => 'required|string',
            'pagos.*.monto'      => 'required|numeric|min:1',
            // Referencias electrónicas (ej. orden de compra)
            'referencias'            => 'nullable|array',
            'referencias.*.numero'   => 'required|string',
            'referencias.*.razon'    => 'nullable|string',
            'referencias.*.code_sii' => 'nullable|integer',
            'referencias.*.fecha'    => 'nullable|date',
            'items'              => 'required|array|min:1',
            'items.*.nombre'     => 'required|string',
            'items.*.cantidad'   => 'required|numeric|min:0.0001',
            'items.*.precio'     => 'required|numeric|min:0',
            'items.*.descuento'  => 'nullable|numeric|min:0|max:100',
            'items.*.producto_id'    => 'nullable|integer',
            'items.*.producto_nombre'=> 'nullable|string',
            'items.*.es_vidrio'  => 'nullable|boolean',
            'items.*.ancho'      => 'nullable|integer',
            'items.*.alto'       => 'nullable|integer',
            'items.*.piezas'     => 'nullable|integer',
            'items.*.pulido'     => 'nullable|boolean',
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

        // Observaciones (nota libre) → en boleta se antepone al primer ítem; en factura va como atributo "Nota"
        $obs = trim($data['observaciones'] ?? '');
        if ($obs !== '' && $esBoleta && !empty($details)) {
            $details[0]['comment'] = mb_substr('[' . $obs . '] ' . $details[0]['comment'], 0, 100);
        }

        // Pagos (uno o varios). La forma de pago "principal" (mayor monto) se guarda para el resumen.
        $payments = collect($data['pagos'])->map(fn ($p) => [
            'paymentTypeId' => $this->paymentTypes[$p['forma_pago']] ?? 1,
            'amount'        => (int) round($p['monto']),
            'recordDate'    => time(),
        ])->all();
        $formaPagoPrincipal = collect($data['pagos'])->sortByDesc('monto')->first()['forma_pago'];

        $payload = [
            'documentTypeId' => $documentTypeId,
            'officeId'       => 1,
            'emissionDate'   => time(),
            'expirationDate' => time(),
            'declareSii'     => 1,
            'clientId'       => $clienteBsaleId,
            'details'        => $details,
            'payments'       => $payments,
        ];

        // Nota en factura/nota de venta (Bsale dynamicAttribute "Nota": factura=6, nota de venta=7)
        if ($obs !== '' && !$esBoleta) {
            $notaAttrId = [5 => 6, 3 => 7][$documentTypeId] ?? null;
            if ($notaAttrId) {
                $payload['dynamicAttributes'] = [[
                    'description'        => $obs,
                    'dynamicAttributeId' => $notaAttrId,
                ]];
            }
        }

        // Referencias electrónicas (ej. Orden de Compra codeSii 801)
        $references = collect($data['referencias'] ?? [])->map(fn ($r) => array_filter([
            'number'        => (string) $r['numero'],
            'referenceDate' => !empty($r['fecha']) ? strtotime($r['fecha']) : time(),
            'reason'        => $r['razon'] ?? null,
            'codeSii'       => isset($r['code_sii']) ? (int) $r['code_sii'] : null,
        ], fn ($v) => $v !== null && $v !== ''))->values()->all();
        if ($references) {
            $payload['references'] = $references;
        }

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
            'neto'                    => (int) round($totalNeto),
            'estado'                  => 'emitido',
            'tipo_documento_bsale_id' => $documentTypeId,
            'forma_pago'              => $formaPagoPrincipal,
            'nota'                    => $obs ?: null,
            'cliente_id'              => $cliente?->id,
            'bsale_cliente_nombre'    => $clienteNombre,
            'id_documento_bsale'      => $bsale['id'] ?? null,
            'numero_documento_bsale'  => $bsale['number'] ?? null,
            'url_pdf_bsale'           => $bsale['urlPdf'] ?? null,
            'fecha_emision'           => now()->toDateString(),
        ]);

        // Guardar líneas de venta (para reimprimir orden de corte y para top de productos)
        foreach ($data['items'] as $it) {
            $neto = (float) $it['precio'];
            $cant = (float) $it['cantidad'];
            $desc = (float) ($it['descuento'] ?? 0);
            \App\Models\DocumentoItem::create([
                'documento_facturacion_id' => $doc->id,
                'producto_id'    => $it['producto_id'] ?? null,
                'nombre'         => $it['producto_nombre'] ?? $it['nombre'],
                'cantidad'       => $cant,
                'precio_unitario'=> (int) round($neto),
                'descuento'      => $desc,
                'total_neto'     => (int) round($neto * $cant * (1 - $desc / 100)),
                'es_vidrio'      => (bool) ($it['es_vidrio'] ?? false),
                'ancho'          => $it['ancho'] ?? null,
                'alto'           => $it['alto'] ?? null,
                'piezas'         => $it['piezas'] ?? null,
                'pulido'         => (bool) ($it['pulido'] ?? false),
            ]);
        }

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

    /**
     * GET /api/ordenes-corte — registro de órdenes de corte (ventas con vidrios), reimprimible.
     */
    public function ordenesCorte(Request $request)
    {
        $docs = DB::table('documentos_facturacion as df')
            ->join('documento_items as di', 'di.documento_facturacion_id', '=', 'df.id')
            ->where('di.es_vidrio', 1)
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%' . $request->buscar . '%';
                $q->where('df.bsale_cliente_nombre', 'like', $t)
                  ->orWhere('df.numero_documento_bsale', 'like', $t);
            })
            ->select('df.id', 'df.numero_documento_bsale', 'df.tipo_documento_bsale_id', 'df.bsale_cliente_nombre', 'df.fecha_emision')
            ->distinct()
            ->orderByDesc('df.id')
            ->limit(300)
            ->get();

        $items = DB::table('documento_items')
            ->whereIn('documento_facturacion_id', $docs->pluck('id'))
            ->where('es_vidrio', 1)
            ->get()
            ->groupBy('documento_facturacion_id');

        return response()->json($docs->map(function ($d) use ($items) {
            $piezas = ($items->get($d->id) ?? collect())->map(fn ($i) => [
                'producto' => $i->nombre,
                'ancho'    => $i->ancho,
                'alto'     => $i->alto,
                'piezas'   => (int) $i->piezas,
                'pulido'   => (bool) $i->pulido,
            ])->values();

            return [
                'id'           => $d->id,
                'numero'       => 'OTC-' . str_pad($d->id, 5, '0', STR_PAD_LEFT),
                'doc_numero'   => $d->numero_documento_bsale,
                'tipo'         => $d->tipo_documento_bsale_id == 1 ? 'Boleta' : 'Factura',
                'cliente'      => $d->bsale_cliente_nombre,
                'fecha'        => $d->fecha_emision,
                'piezas'       => $piezas,
                'total_piezas' => $piezas->sum('piezas'),
            ];
        }));
    }

    private function nombreCliente(Cliente $c): string
    {
        return $c->razon_social
            ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''))
            ?: 'Cliente';
    }
}
