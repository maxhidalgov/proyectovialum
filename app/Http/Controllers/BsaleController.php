<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BsaleController extends Controller
{
    private $baseUrl;
    private $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.bsale.base_url', 'https://api.bsale.cl/v1/');
        $this->accessToken = config('services.bsale.access_token');
    }

    /**
     * Obtener clientes sincronizados con Bsale
     */
    public function getClientesSincronizados()
    {
        try {
            $clientes = Cliente::whereNotNull('bsale_id')
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'identification', 'bsale_id']);

            return response()->json([
                'success' => true,
                'clientes' => $clientes
            ]);
        } catch (\Exception $e) {
            Log::error("Error obteniendo clientes sincronizados:", [
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener clientes sincronizados'
            ], 500);
        }
    }

    /**
     * Obtener headers para autenticación con BSALE
     */
    private function getBsaleHeaders()
    {
        return [
            'access_token' => $this->accessToken,
            'Accept' => 'application/json'
        ];
    }

    /**
     * Crear documento electrónico en BSALE desde cotización
     */
    public function crearDocumentoDesdeCotzacion(Request $request)
    {
        try {
            Log::info("🎯 INICIO: Petición recibida en crearDocumentoDesdeCotzacion");
            Log::info("📦 Request data", $request->all());
            
            $cotizacionId         = $request->input('cotizacion_id');
            $tipoDocumento        = $request->input('tipo_documento');
            $clienteFacturacionId = $request->input('cliente_facturacion_id');
            $metodoPago           = $request->input('metodo_pago');
            $condicionesPago      = $request->input('condiciones_pago');
            $fechaVencimiento     = $request->input('fecha_vencimiento');
            $observaciones        = $request->input('observaciones', '');
            $porcentaje           = (float) $request->input('porcentaje', 100); // 100 = total completo
            $tipoEmision          = $porcentaje == 100 ? 'total' : ($porcentaje <= 50 ? 'anticipo' : 'saldo');
            
            // Buscar cotización
            $cotizacion = Cotizacion::with([
                    'ventanas.tipoVentana',
                    'cliente',
                    'detalles.listaPrecio.producto',
                    'detalles.producto',
                ])->findOrFail($cotizacionId);

            // Determinar qué cliente usar para facturación
            $clienteFacturacion = null;
            $clienteBsaleId = null;
            
            // Si es BOLETA ELECTRÓNICA (tipo 1), puede no requerir cliente específico
            if ($tipoDocumento == 1) {
                // Para boletas, intentar usar cliente de facturación si existe
                if ($clienteFacturacionId) {
                    $clienteFacturacion = Cliente::find($clienteFacturacionId);
                    $clienteBsaleId = $clienteFacturacion->bsale_id ?? null;
                } elseif ($cotizacion->cliente_facturacion_id) {
                    $clienteFacturacion = Cliente::find($cotizacion->cliente_facturacion_id);
                    $clienteBsaleId = $clienteFacturacion->bsale_id ?? null;
                }
                
                // Si no hay cliente o no tiene bsale_id, usar cliente genérico "Consumidor Final"
                // ID 1 es típicamente el consumidor final en Bsale
                if (!$clienteBsaleId) {
                    $clienteBsaleId = 1; // ID de "Consumidor Final" en Bsale
                    Log::info("📝 Boleta sin cliente específico, usando Consumidor Final (ID: 1)");
                }
            } else {
                // Para FACTURAS y otros documentos, cliente ES OBLIGATORIO
                if ($clienteFacturacionId) {
                    $clienteFacturacion = Cliente::find($clienteFacturacionId);
                } elseif ($cotizacion->cliente_facturacion_id) {
                    $clienteFacturacion = Cliente::find($cotizacion->cliente_facturacion_id);
                } else {
                    $clienteFacturacion = $cotizacion->cliente;
                }

                // Verificar que el cliente de facturación tenga bsale_id
                if (!$clienteFacturacion || !$clienteFacturacion->bsale_id) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Para facturas, el cliente debe estar sincronizado con Bsale. Por favor selecciona un cliente que tenga RUT registrado en Bsale.'
                    ], 400);
                }
                
                $clienteBsaleId = $clienteFacturacion->bsale_id;
            }

            // Actualizar cliente_facturacion_id en la cotización si se proporcionó uno nuevo
            if ($clienteFacturacionId && $cotizacion->cliente_facturacion_id != $clienteFacturacionId) {
                $cotizacion->update(['cliente_facturacion_id' => $clienteFacturacionId]);
            }
            
            Log::info("🧾 Creando documento BSALE", [
                'cotizacion_id' => $cotizacionId,
                'tipo_documento' => $tipoDocumento,
                'tipo_documento_nombre' => $tipoDocumento == 1 ? 'Boleta' : ($tipoDocumento == 5 ? 'Factura' : 'Otro'),
                'cliente_cotizacion' => $cotizacion->cliente->razon_social ?? 'Sin cliente',
                'cliente_facturacion' => $clienteFacturacion ? $clienteFacturacion->razon_social : 'Consumidor Final',
                'cliente_bsale_id' => $clienteBsaleId,
                'metodo_pago' => $metodoPago,
                'condiciones_pago' => $condicionesPago
            ]);

            Log::info("🔍 Cotización encontrada", [
                'id' => $cotizacion->id,
                'ventanas_count' => $cotizacion->ventanas->count(),
                'cliente_cotizacion' => $cotizacion->cliente->razon_social ?? 'Sin cliente',
                'cliente_facturacion' => $clienteFacturacion->razon_social
            ]);

            if ($cotizacion->estado_cotizacion_id != 2) { // 2 = Aprobada
                return response()->json([
                    'success' => false,
                    'error' => 'La cotización debe estar aprobada para generar documento'
                ], 400);
            }

            // Construir payload para BSALE
            try {
                $payload = $this->construirPayloadBsale(
                    $cotizacion,
                    $tipoDocumento,
                    $clienteBsaleId,
                    $metodoPago,
                    $condicionesPago,
                    $fechaVencimiento,
                    $observaciones,
                    $porcentaje
                );
                Log::info("✅ Payload construido exitosamente");
            } catch (\Exception $e) {
                Log::error("❌ Error construyendo payload:", [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine()
                ]);
                throw $e;
            }

            Log::info("📤 Payload BSALE:", $payload);

            // Enviar a BSALE API
            $response = $this->enviarDocumentoBsale($payload);
            Log::info("📥 Respuesta de BSALE:", $response);

            if ($response['success']) {
                $monto = round($cotizacion->total * $porcentaje / 100);

                // Guardar documento de facturación
                $docFact = \App\Models\DocumentoFacturacion::create([
                    'cotizacion_id'          => $cotizacion->id,
                    'tipo'                   => $tipoEmision,
                    'porcentaje'             => $porcentaje,
                    'monto'                  => $monto,
                    'estado'                 => 'emitido',
                    'id_documento_bsale'     => $response['data']['id'] ?? null,
                    'numero_documento_bsale' => $response['data']['number'] ?? null,
                    'url_pdf_bsale'          => $response['data']['urlPdf'] ?? null,
                    'fecha_emision'          => now()->toDateString(),
                ]);

                // Calcular total emitido para decidir si cambiar estado
                $totalEmitido = \App\Models\DocumentoFacturacion::where('cotizacion_id', $cotizacion->id)
                    ->where('estado', 'emitido')
                    ->sum('monto');
                $estaCompleto = $totalEmitido >= $cotizacion->total;

                $cotizacion->update([
                    'numero_documento_bsale' => $response['data']['number'] ?? null,
                    'id_documento_bsale'     => $response['data']['id'] ?? null,
                    'fecha_documento_bsale'  => now(),
                    'url_pdf_bsale'          => $response['data']['urlPdf'] ?? null,
                    'token_bsale'            => $response['data']['token'] ?? null,
                    // Solo marcar como Facturada si se emitió el 100%
                    ...($estaCompleto ? ['estado_cotizacion_id' => 6] : []),
                ]);

                return response()->json([
                    'success'   => true,
                    'message'   => 'Documento creado exitosamente',
                    'documento' => $response['data'],
                    'doc_fact'  => $docFact,
                    'cotizacion' => $cotizacion->fresh(['documentosFacturacion']),
                ]);
            } else {
                Log::error("❌ Error BSALE:", $response);
                return response()->json([
                    'success' => false,
                    'error' => 'Error al crear documento en BSALE',
                    'details' => $response['error'] ?? 'Error desconocido'
                ], 500);
            }

        } catch (\Exception $e) {
            // DEBUGGING: Enviar el error completo al cliente
            $errorDetails = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            
            Log::error("💥 Excepción al crear documento BSALE:", $errorDetails);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage(),
                'debug' => $errorDetails // TEMPORAL para debugging
            ], 500);
        }
    }

    /**
     * Construir payload según especificaciones BSALE
     */
    private function construirPayloadBsale(
        $cotizacion,
        $tipoDocumento,
        $clienteBsaleId,
        $metodoPago,
        $condicionesPago,
        $fechaVencimiento,
        $observaciones,
        $porcentaje = 100
    ) {
        $detalles = [];
        $totalNeto = 0;
        // Descuento inverso: si cobro 30%, aplico 70% de descuento en cada línea
        $descuentoLinea = $porcentaje < 100 ? round(100 - $porcentaje, 4) : 0;

        Log::info("🏗️ Construyendo payload", [
            'ventanas_count' => $cotizacion->ventanas->count(),
            'cliente_direccion' => $cotizacion->cliente->direccion ?? 'Sin dirección'
        ]);

        // Procesar ventanas
        foreach ($cotizacion->ventanas as $ventana) {
            $precioUnitario = $ventana->precio ?? 0;
            $cantidad = (int)($ventana->cantidad ?? 1);
            $netoUnitario = $precioUnitario / 1.19;

            $detalles[] = [
                'netUnitValue' => round((float)$netoUnitario, 4),
                'quantity'     => $cantidad,
                'taxId'        => '[1]',
                'comment'      => $this->generarDescripcionVentana($ventana),
                'discount'     => $descuentoLinea,
            ];

            $totalNeto += $netoUnitario * $cantidad;
        }

        // Procesar productos/detalles
        // Vidrios: agrupar por nombre de producto sumando m2
        // No-vidrios: agrupar por nombre sumando cantidad
        $grupos = [];
        foreach ($cotizacion->detalles as $detalle) {
            $nombreProducto = $detalle->listaPrecio?->producto?->nombre
                ?? $detalle->producto?->nombre
                ?? $detalle->descripcion
                ?? 'Producto';

            $esVidrio = (bool)($detalle->es_vidrio ?? $detalle->esVidrio ?? false);

            if ($esVidrio && $detalle->m2 > 0) {
                // Clave de grupo: nombre del producto
                $clave = $nombreProducto;
                if (!isset($grupos[$clave])) {
                    $grupos[$clave] = [
                        'descripcion'   => $nombreProducto,
                        'es_vidrio'     => true,
                        'm2_total'      => 0,
                        'precio_por_m2' => 0,
                        'muestras'      => 0,
                    ];
                }
                $grupos[$clave]['m2_total']      += (float)$detalle->m2;
                // precio_unitario es por el trozo, precio_por_m2 = precio_unitario / m2
                $grupos[$clave]['precio_por_m2'] += ($detalle->m2 > 0)
                    ? ((float)$detalle->precio_unitario / (float)$detalle->m2)
                    : 0;
                $grupos[$clave]['muestras']++;
            } else {
                $clave = $nombreProducto;
                if (!isset($grupos[$clave])) {
                    $grupos[$clave] = [
                        'descripcion'       => $nombreProducto,
                        'es_vidrio'         => false,
                        'cantidad'          => 0,
                        'precio_unitario'   => (float)$detalle->precio_unitario,
                    ];
                }
                $grupos[$clave]['cantidad'] += (int)($detalle->cantidad ?? 1);
            }
        }

        foreach ($grupos as $grupo) {
            if ($grupo['es_vidrio']) {
                // Precio por m2 promedio (en caso de distintos precios)
                $precioPorM2 = $grupo['muestras'] > 0
                    ? $grupo['precio_por_m2'] / $grupo['muestras']
                    : 0;
                $netoUnitario = $precioPorM2 / 1.19;
                $cantidad     = round($grupo['m2_total'], 4);
                $descripcion  = $grupo['descripcion'] . ' (m²)';
            } else {
                $netoUnitario = (float)$grupo['precio_unitario'] / 1.19;
                $cantidad     = $grupo['cantidad'];
                $descripcion  = $grupo['descripcion'];
            }

            $detalles[] = [
                'netUnitValue' => round($netoUnitario, 4),
                'quantity'     => $cantidad,
                'taxId'        => '[1]',
                'comment'      => $descripcion,
                'discount'     => $descuentoLinea,
            ];

            $totalNeto += $netoUnitario * $cantidad;
        }

        $label = $porcentaje <= 50 ? 'Anticipo' : 'Saldo';
        $nota  = $porcentaje < 100
            ? "{$label} {$porcentaje}% sobre cotización #{$cotizacion->id}. Total cotización: $" . number_format($cotizacion->total, 0, ',', '.')
            : null;

        // Opción B: Boleta no tiene dynamicAttribute "Nota" → prefixar primer item
        if ($tipoDocumento == 1 && $nota !== null && !empty($detalles)) {
            $detalles[0]['comment'] = "[{$label} {$porcentaje}%] " . $detalles[0]['comment'];
        }

        // Formato correcto según documentación BSALE
        $payload = [
            "documentTypeId" => (int)$tipoDocumento,
            "officeId"       => 1,
            "emissionDate"   => time(),
            "expirationDate" => time(),
            "declareSii"     => 1,
            "clientId"       => (int)$clienteBsaleId,
            "details"        => $detalles,
        ];

        if ($nota) {
            // El dynamicAttributeId de "Nota" varía por tipo de documento en esta cuenta:
            // Cotización (24) → 25 | Nota de Venta (3) → 7 | Factura Electrónica (5) → 6 | Boleta (1) → sin atributo
            $notaAttrMap = [24 => 25, 3 => 7, 5 => 6];
            $notaAttrId  = $notaAttrMap[$tipoDocumento] ?? null;

            if ($notaAttrId) {
                $payload['dynamicAttributes'] = [
                    [
                        'description'        => $nota,
                        'dynamicAttributeId' => $notaAttrId,
                    ]
                ];
            }
        }

        // Agregar fecha de vencimiento específica si aplica
        if ($condicionesPago !== 'contado' && $fechaVencimiento) {
            $payload['expirationDate'] = strtotime($fechaVencimiento);
        }

        // Agregar método de pago
        if ($metodoPago) {
            // El monto del pago es el total del documento (ya con descuento aplicado)
            $montoDocumento = $porcentaje < 100
                ? (int)round($cotizacion->total * $porcentaje / 100)
                : (int)round($totalNeto * 1.19);
            $payload['payments'] = [
                [
                    "paymentTypeId" => $this->getPaymentTypeId($metodoPago),
                    "amount"        => $montoDocumento,
                    "recordDate"    => time(),
                ]
            ];
        }

        Log::info("🧾 PAYLOAD BSALE FINAL:", [
            'payload' => $payload,
            'details_count' => count($detalles),
            'total_neto' => $totalNeto,
            'total_con_iva' => $totalNeto * 1.19
        ]);

        return $payload;
    }

    /**
     * Generar descripción detallada de la ventana
     */
    private function generarDescripcionVentana($ventana)
    {
        $tipo = $ventana->tipoVentana->nombre ?? 'Ventana';
        $dimensiones = "{$ventana->ancho}mm x {$ventana->alto}mm";
        
        $descripcion = "{$tipo} {$dimensiones}";
        
        return $descripcion;
    }

    /**
     * Obtener ID de tipo de pago para BSALE
     */
    private function getPaymentTypeId($metodoPago)
    {
        // IDs reales de la cuenta BSALE (verificados via API payment_types.json)
        $paymentTypes = [
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

        return $paymentTypes[$metodoPago] ?? 1; // Por defecto efectivo
    }

    /**
     * Enviar documento a BSALE API
     */
    private function enviarDocumentoBsale($payload)
    {
        try {
            if (!$this->accessToken) {
                return [
                    'success' => false,
                    'error' => 'Token de acceso BSALE no configurado'
                ];
            }

            Log::info("📡 ENVIANDO PAYLOAD A BSALE:", [
                'url' => $this->baseUrl . 'documents.json',
                'payload' => $payload,
                'headers' => [
                    'access_token' => substr($this->accessToken, 0, 10) . '...',
                    'Content-Type' => 'application/json'
                ]
            ]);

            $response = Http::withHeaders([
                'access_token' => $this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout(30)
            ->post($this->baseUrl . 'documents.json', $payload);

            Log::info("📨 RESPUESTA BSALE:", [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            } else {
                Log::error("❌ ERROR RESPUESTA BSALE:", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'json' => $response->json()
                ]);

                return [
                    'success' => false,
                    'error' => 'Error en API BSALE',
                    'status' => $response->status(),
                    'details' => $response->json() ?? $response->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error("💥 EXCEPCIÓN enviando a BSALE:", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error de conexión con BSALE',
                'message' => $e->getMessage()
            ];
        }
    }



    /**
     * Obtener oficinas disponibles en BSALE
     */
    public function getOficinas()
    {
        try {
            if (!$this->accessToken) {
                return response()->json([
                    'success' => true,
                    'oficinas' => [
                        'items' => [
                            ['id' => 1, 'name' => 'Oficina Principal']
                        ]
                    ]
                ]);
            }

            $response = Http::withHeaders([
                'access_token' => $this->accessToken,
                'Accept' => 'application/json'
            ])
            ->timeout(15)
            ->get($this->baseUrl . 'offices.json');

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'oficinas' => $response->json()
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'oficinas' => [
                        'items' => [
                            ['id' => 1, 'name' => 'Oficina Principal']
                        ]
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Error obteniendo oficinas BSALE:", [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => true,
                'oficinas' => [
                    'items' => [
                        ['id' => 1, 'name' => 'Oficina Principal']
                    ]
                ]
            ]);
        }
    }

    /**
     * Obtener clientes de BSALE
     */
    public function getClientes(Request $request)
    {
        try {
            if (!$this->accessToken) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token de acceso BSALE no configurado'
                ], 400);
            }

            $search = $request->input('search', '');
            $limit = $request->input('limit', 50);
            
            $url = $this->baseUrl . 'clients.json';
            $params = ['limit' => $limit];
            
            if ($search) {
                $params['search'] = $search;
            }

            $response = Http::withHeaders([
                'access_token' => $this->accessToken,
                'Accept' => 'application/json'
            ])
            ->timeout(15)
            ->get($url, $params);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'clientes' => $response->json()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Error al obtener clientes de BSALE',
                    'details' => $response->json()
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Error obteniendo clientes BSALE:", [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error de conexión con BSALE',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear cliente en BSALE si no existe
     */
    public function crearCliente(Request $request)
    {
        try {
            if (!$this->accessToken) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token de acceso BSALE no configurado'
                ], 400);
            }

            $payload = [
                "firstName" => $request->input('nombre'),
                "lastName" => $request->input('apellido', ''),
                "company" => $request->input('empresa', ''),
                "activity" => $request->input('giro', ''),
                "municipality" => $request->input('comuna_id', 1),
                "address" => $request->input('direccion', ''),
                "phone" => $request->input('telefono', ''),
                "email" => $request->input('email', ''),
                "code" => $request->input('rut', ''),
                "sendEmail" => $request->input('enviar_email', false)
            ];

            $response = Http::withHeaders([
                'access_token' => $this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout(15)
            ->post($this->baseUrl . 'clients.json', $payload);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'cliente' => $response->json()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Error al crear cliente en BSALE',
                    'details' => $response->json()
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Error creando cliente BSALE:", [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al crear cliente en BSALE',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener documento específico de BSALE
     */
    public function getDocumento($documentoId)
    {
        try {
            if (!$this->accessToken) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token de acceso BSALE no configurado'
                ], 400);
            }

            $response = Http::withHeaders([
                'access_token' => $this->accessToken,
                'Accept' => 'application/json'
            ])
            ->timeout(15)
            ->get($this->baseUrl . "documents/{$documentoId}.json");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'documento' => $response->json()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Documento no encontrado',
                    'details' => $response->json()
                ], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo documento',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar PDF del documento
     */
    public function descargarPdf($documentoId)
    {
        try {
            if (!$this->accessToken) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token de acceso BSALE no configurado'
                ], 400);
            }

            $response = Http::withHeaders([
                'access_token' => $this->accessToken,
                'Accept' => 'application/json'
            ])
            ->timeout(30)
            ->get($this->baseUrl . "documents/{$documentoId}.json");

            if ($response->successful()) {
                $documento = $response->json();
                return response()->json([
                    'success' => true,
                    'pdf_url' => $documento['urlPdfOrigin'] ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Error al obtener PDF',
                    'details' => $response->json()
                ], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo PDF',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar documento por email
     */
    public function enviarEmail(Request $request, $documentoId)
    {
        try {
            if (!$this->accessToken) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token de acceso BSALE no configurado'
                ], 400);
            }

            $email = $request->input('email');
            $mensaje = $request->input('mensaje', '');

            $payload = [
                'email' => $email,
                'message' => $mensaje
            ];

            $response = Http::withHeaders([
                'access_token' => $this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout(30)
            ->post($this->baseUrl . "documents/{$documentoId}/send.json", $payload);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Documento enviado por email exitosamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar email',
                    'details' => $response->json()
                ], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error enviando email',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipos de documento disponibles en BSALE
     */
    public function getTiposDocumento()
    {
        try {
            $response = Http::withHeaders($this->getBsaleHeaders())
                ->timeout(30)
                ->get($this->baseUrl . 'document_types.json');

            if ($response->successful()) {
                $data = $response->json();
                
                // Filtrar solo documentos de venta (use: 0) y no creditNote
                $tiposVenta = collect($data['items'])->filter(function ($tipo) {
                    return $tipo['use'] == 0 && !$tipo['isCreditNote'] && $tipo['state'] == 0;
                })->map(function ($tipo) {
                    return [
                        'id' => $tipo['id'],
                        'name' => $tipo['name'],
                        'codeSii' => $tipo['codeSii'] ?? '',
                        'isElectronic' => $tipo['isElectronicDocument'],
                        'description' => $this->getDescripcionTipoDocumento($tipo)
                    ];
                })->values();

                return response()->json([
                    'success' => true,
                    'tipos_documento' => $tiposVenta
                ]);
            } else {
                Log::error("Error obteniendo tipos documento BSALE:", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error consultando BSALE'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Excepción obteniendo tipos documento:", [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error de conexión con BSALE'
            ], 500);
        }
    }

    /**
     * Obtener descripción amigable del tipo de documento
     */
    private function getDescripcionTipoDocumento($tipo)
    {
        if ($tipo['isElectronicDocument']) {
            return 'Documento tributario electrónico';
        } elseif ($tipo['isSalesNote']) {
            return 'Documento de preventa';
        } else {
            return 'Documento físico';
        }
    }

    /**
     * Listar atributos dinámicos de la cuenta Bsale (para encontrar el ID de Observaciones)
     */
    public function getDynamicAttributes()
    {
        $response = Http::withHeaders($this->getBsaleHeaders())
            ->get($this->baseUrl . 'dynamic_attributes.json');
        return response()->json($response->json());
    }

    /**
     * Test de conexión con BSALE
     */
    public function testConexion()
    {
        try {
            if (!$this->accessToken) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token de acceso BSALE no configurado',
                    'config' => [
                        'base_url' => $this->baseUrl,
                        'token_configured' => false
                    ]
                ]);
            }

            $response = Http::withHeaders([
                'access_token' => $this->accessToken,
                'Accept' => 'application/json'
            ])
            ->timeout(10)
            ->get($this->baseUrl . 'clients.json?limit=1');

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Conexión exitosa con BSALE',
                    'config' => [
                        'base_url' => $this->baseUrl,
                        'token_configured' => true
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Error de autenticación con BSALE',
                    'status' => $response->status(),
                    'config' => [
                        'base_url' => $this->baseUrl,
                        'token_configured' => true
                    ]
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de conexión con BSALE',
                'message' => $e->getMessage(),
                'config' => [
                    'base_url' => $this->baseUrl,
                    'token_configured' => !empty($this->accessToken)
                ]
            ]);
        }
    }
}