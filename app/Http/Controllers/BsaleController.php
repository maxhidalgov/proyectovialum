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
            
            $cotizacionId = $request->input('cotizacion_id');
            $tipoDocumento = $request->input('tipo_documento'); // 1=factura, 8=boleta, etc.
            $clienteFacturacionId = $request->input('cliente_facturacion_id'); // ID del cliente que facturará
            $metodoPago = $request->input('metodo_pago');
            $condicionesPago = $request->input('condiciones_pago');
            $fechaVencimiento = $request->input('fecha_vencimiento');
            $observaciones = $request->input('observaciones', '');
            
            // Buscar cotización
            $cotizacion = Cotizacion::with(['ventanas.tipoVentana', 'cliente'])
                ->findOrFail($cotizacionId);

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
                    $observaciones
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
            Log::info("🚀 Enviando a BSALE API...");
            $response = $this->enviarDocumentoBsale($payload);
            Log::info("📥 Respuesta de BSALE:", $response);

            if ($response['success']) {
                // Actualizar cotización con datos del documento
                $cotizacion->update([
                    'numero_documento_bsale' => $response['data']['number'] ?? null,
                    'id_documento_bsale' => $response['data']['id'] ?? null,
                    'fecha_documento_bsale' => now(),
                    'estado_facturacion' => 'facturada',
                    'estado_cotizacion_id' => 6, // Cambiar a "Facturada" (ID 6)
                    'url_pdf_bsale' => $response['data']['urlPdf'] ?? null,
                    'token_bsale' => $response['data']['token'] ?? null
                ]);

                Log::info("✅ Documento BSALE creado", [
                    'bsale_id' => $response['data']['id'],
                    'numero' => $response['data']['number'],
                    'pdf_url' => $response['data']['urlPdf'] ?? 'No disponible',
                    'token' => $response['data']['token'] ?? 'No disponible'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Documento creado exitosamente',
                    'documento' => $response['data'],
                    'cotizacion' => $cotizacion->fresh()
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
        $observaciones
    ) {
        $detalles = [];
        $totalNeto = 0;

        Log::info("🏗️ Construyendo payload", [
            'ventanas_count' => $cotizacion->ventanas->count(),
            'cliente_direccion' => $cotizacion->cliente->direccion ?? 'Sin dirección'
        ]);

        // Procesar cada ventana de la cotización (formato BSALE correcto)
        foreach ($cotizacion->ventanas as $index => $ventana) {
            $precioUnitario = $ventana->precio ?? 0;
            $cantidad = (int)($ventana->cantidad ?? 1);
            
            // Calcular neto unitario (precio sin IVA)
            $netoUnitario = $precioUnitario / 1.19; // Asumiendo IVA 19%

            $detalles[] = [
                "netUnitValue" => (float)$netoUnitario, // BSALE espera float, no entero
                "quantity" => $cantidad,
                "taxId" => "[1]", // IVA 19% - ID de impuesto 1
                "comment" => $this->generarDescripcionVentana($ventana),
                "discount" => 0
                // Sin variantId ni code para crear producto dinámico
            ];

            $totalNeto += $netoUnitario * $cantidad;
        }

        // Formato correcto según documentación BSALE
        $payload = [
            "documentTypeId" => (int)$tipoDocumento,
            "officeId" => 1, // ID de sucursal por defecto
            "emissionDate" => time(), // Timestamp Unix
            "expirationDate" => time(), // Fecha de vencimiento por defecto (mismo día)
            "declareSii" => 1,
            "clientId" => (int)$clienteBsaleId, // Usar clientId en lugar de client object
            "details" => $detalles
        ];

        // Agregar fecha de vencimiento específica si aplica
        if ($condicionesPago !== 'contado' && $fechaVencimiento) {
            $payload['expirationDate'] = strtotime($fechaVencimiento);
        }

        // Agregar método de pago
        if ($metodoPago) {
            $totalConIva = $totalNeto * 1.19;
            $payload['payments'] = [
                [
                    "paymentTypeId" => $this->getPaymentTypeId($metodoPago),
                    "amount" => (int)round($totalConIva),
                    "recordDate" => time()
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
        $paymentTypes = [
            'efectivo' => 1,
            'transferencia' => 2,
            'tarjeta_credito' => 3,
            'tarjeta_debito' => 4,
            'cheque' => 5
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