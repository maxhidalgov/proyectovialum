<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Cotizacion;
use App\Models\EstadoCotizacion;
use App\Models\Producto;
use App\Models\ProductoColorProveedor;
use App\Models\TipoMaterial;
use App\Models\TipoVentana;
use App\Models\Ventana;
use App\Services\CalculoVentanaService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AgenteController extends Controller
{
    public function __construct(private CalculoVentanaService $calculoService) {}

    public function cotizar(Request $request)
    {
        try {
        $messages = $request->input('messages', []);
        $user = Auth::guard('api')->user();

        $maxIterations = 10;
        $iteration = 0;
        $content = [];
        $cotizacionCreada = null;

        do {
            $iteration++;
            $response = $this->callClaude($messages);

            if (isset($response['error'])) {
                return response()->json(['error' => $response['error']], 500);
            }

            $stopReason = $response['stop_reason'];
            $content    = $response['content'];

            if ($stopReason === 'tool_use') {
                $messages[] = ['role' => 'assistant', 'content' => $content];

                $toolResults = [];
                foreach ($content as $block) {
                    if ($block['type'] === 'tool_use') {
                        $result = $this->executeTool($block['name'], $block['input'], $user);

                        if ($block['name'] === 'crear_cotizacion' && isset($result['cotizacion_id'])) {
                            $cotizacionCreada = $result['cotizacion_id'];
                        }

                        $toolResults[] = [
                            'type'        => 'tool_result',
                            'tool_use_id' => $block['id'],
                            'content'     => json_encode($result),
                        ];
                    }
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];
            }
        } while ($stopReason === 'tool_use' && $iteration < $maxIterations);

        $text = '';
        foreach ($content as $block) {
            if ($block['type'] === 'text') {
                $text .= $block['text'];
            }
        }

        // Agregar la respuesta final al historial
        $messages[] = ['role' => 'assistant', 'content' => $content];

        return response()->json([
            'message'           => $text,
            'messages'          => $messages,
            'cotizacion_creada' => $cotizacionCreada,
        ]);
        } catch (\Exception $e) {
            \Log::error('AgenteController cotizar: ' . get_class($e) . ' — ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function callClaude(array $messages): array
    {
        $client = new Client(['timeout' => 90]);

        try {
            $response = $client->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key'         => config('services.anthropic.key'),
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => 'claude-sonnet-4-6',
                    'max_tokens' => 2048,
                    'system'     => $this->getSystemPrompt(),
                    'tools'      => $this->getTools(),
                    'messages'   => $this->normalizeMessages($messages),
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $body = $e->getResponse()->getBody()->getContents();
            \Log::error('Anthropic API ClientError: ' . $body);
            return ['error' => 'Anthropic: ' . $body];
        } catch (\Exception $e) {
            \Log::error('AgenteController callClaude: ' . get_class($e) . ' — ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    private function executeTool(string $name, array $input, $user): array
    {
        return match ($name) {
            'buscar_cliente'          => $this->toolBuscarCliente($input),
            'crear_cliente'           => $this->toolCrearCliente($input),
            'listar_tipos_ventana'    => $this->toolListarTiposVentana(),
            'listar_colores'          => $this->toolListarColores(),
            'listar_vidrios'          => $this->toolListarVidrios(),
            'calcular_precio_ventana' => $this->toolCalcularPrecio($input),
            'crear_cotizacion'        => $this->toolCrearCotizacion($input, $user),
            default                   => ['error' => "Tool '{$name}' no encontrada"],
        };
    }

    // PHP decodes JSON {} as [] — Anthropic requires tool_use.input to be {} not [].
    // This fix must run on every outbound messages array (including ones from the frontend).
    private function normalizeMessages(array $messages): array
    {
        return array_map(function ($msg) {
            if (($msg['role'] ?? '') === 'assistant' && is_array($msg['content'])) {
                $msg['content'] = array_map(function ($block) {
                    if (($block['type'] ?? '') === 'tool_use' && is_array($block['input'])) {
                        $block['input'] = $this->toObject($block['input']);
                    }
                    return $block;
                }, $msg['content']);
            }
            return $msg;
        }, $messages);
    }

    private function toObject(array $arr): mixed
    {
        if (empty($arr) || array_keys($arr) !== range(0, count($arr) - 1)) {
            $obj = new \stdClass();
            foreach ($arr as $k => $v) {
                $obj->$k = is_array($v) ? $this->toObject($v) : $v;
            }
            return $obj;
        }
        return array_map(fn($v) => is_array($v) ? $this->toObject($v) : $v, $arr);
    }

    private function toolBuscarCliente(array $input): array
    {
        $q = $input['q'] ?? '';
        return Cliente::where('razon_social', 'like', "%{$q}%")
            ->orWhere('first_name', 'like', "%{$q}%")
            ->orWhere('last_name', 'like', "%{$q}%")
            ->orWhere('identification', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%")
            ->limit(5)
            ->get(['id', 'razon_social', 'first_name', 'last_name', 'email', 'phone', 'identification'])
            ->map(fn($c) => [
                'id'       => $c->id,
                'nombre'   => $c->razon_social ?: trim("{$c->first_name} {$c->last_name}"),
                'email'    => $c->email,
                'telefono' => $c->phone,
                'rut'      => $c->identification,
            ])->toArray();
    }

    private function toolCrearCliente(array $input): array
    {
        $cliente = Cliente::create([
            'first_name'   => $input['nombre'] ?? null,
            'last_name'    => $input['apellido'] ?? null,
            'email'        => $input['email'] ?? null,
            'phone'        => $input['telefono'] ?? null,
            'razon_social' => $input['razon_social'] ?? null,
            'identification' => $input['rut'] ?? null,
        ]);

        return [
            'id'     => $cliente->id,
            'nombre' => $cliente->razon_social ?: trim("{$cliente->first_name} {$cliente->last_name}"),
        ];
    }

    private function toolListarTiposVentana(): array
    {
        return TipoVentana::all(['id', 'nombre'])->toArray();
    }

    private function toolListarColores(): array
    {
        return Color::all(['id', 'nombre'])->toArray();
    }

    private function toolListarVidrios(): array
    {
        $productos = Producto::with(['coloresPorProveedor.proveedor', 'tipoProducto'])
            ->where(function ($q) {
                $q->where('nombre', 'like', '%idrio%')
                  ->orWhere('nombre', 'like', '%onolítico%')
                  ->orWhere('nombre', 'like', '%ermopanel%')
                  ->orWhere('nombre', 'like', '%DVH%');
            })
            ->orWhereHas('tipoProducto', fn($q) => $q->where('nombre', 'like', '%idrio%'))
            ->get();

        $vidrios = [];
        foreach ($productos as $p) {
            $nombre = strtolower($p->nombre);
            $tipo = str_contains($nombre, 'termopanel') || str_contains($nombre, 'dvh') ? 'Termopanel' : 'Monolítico';

            foreach ($p->coloresPorProveedor as $pcp) {
                $vidrios[] = [
                    'pcp_id'       => $pcp->id,
                    'producto_id'  => $p->id,
                    'proveedor_id' => $pcp->proveedor_id,
                    'nombre'       => $p->nombre,
                    'tipo'         => $tipo,
                    'proveedor'    => $pcp->proveedor->nombre ?? 'N/A',
                    'costo'        => $pcp->costo,
                ];
            }
        }

        return $vidrios;
    }

    private function toolCalcularPrecio(array $input): array
    {
        try {
            $base = [
                'tipo'            => $input['tipo_ventana_id'],
                'ancho'           => $input['ancho'],
                'alto'            => $input['alto'],
                'color'           => $input['color_id'],
                'cantidad'        => $input['cantidad'] ?? 1,
                'productoVidrio'  => $input['producto_vidrio_id'],
                'proveedorVidrio' => $input['proveedor_vidrio_id'],
                'tipoVidrio'      => $input['tipo_vidrio'],
                'manillon'        => $input['manillon'] ?? false,
                'hojas_totales'   => $input['hojas_totales'] ?? 2,
                'hojas_moviles'   => $input['hojas_moviles'] ?? 1,
            ];

            $materiales = $this->calculoService->calcularMateriales($base);
            $costoTotal = array_sum(array_column($materiales, 'costo_total'));

            $margen = 0.50;
            $tipoVentana = TipoVentana::with('material')->find($input['tipo_ventana_id']);
            if ($tipoVentana?->material) {
                $tipoMaterial = TipoMaterial::where('nombre', 'like', '%' . $tipoVentana->material->nombre . '%')->first()
                    ?? TipoMaterial::first();
                $margen = (float) ($tipoMaterial->margen ?? 0.50);
            }

            if ($costoTotal > 0) {
                $precio = (int) ceil($costoTotal / (1 - $margen));
                return [
                    'costo_unitario'  => $costoTotal,
                    'precio_unitario' => $precio,
                    'precio_total'    => $precio * ($input['cantidad'] ?? 1),
                    'margen_aplicado' => $margen,
                ];
            }

            // Diagnóstico: probar con otro color para aislar si el problema es el color o el vidrio
            $otroColor = \App\Models\Color::where('id', '!=', $input['color_id'])->first();
            $costoOtroColor = 0;
            if ($otroColor) {
                $mat2 = $this->calculoService->calcularMateriales(array_merge($base, ['color' => $otroColor->id]));
                $costoOtroColor = array_sum(array_column($mat2, 'costo_total'));
            }

            if ($costoOtroColor > 0) {
                return [
                    'costo_unitario'  => 0,
                    'precio_unitario' => 0,
                    'precio_total'    => 0,
                    'diagnostico'     => "El color seleccionado (color_id={$input['color_id']}) no tiene perfiles configurados para este tipo de ventana. El vidrio y el tipo de ventana SÍ son válidos — solo hay que cambiar el color del marco.",
                ];
            }

            return [
                'costo_unitario'  => 0,
                'precio_unitario' => 0,
                'precio_total'    => 0,
                'diagnostico'     => "No se pudo calcular precio para esta combinación. Verifica que el tipo de ventana, el producto de vidrio y el proveedor sean correctos.",
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolCrearCotizacion(array $input, $user): array
    {
        DB::beginTransaction();
        try {
            $estado = EstadoCotizacion::where('nombre', 'Evaluación')->first();

            $total = array_sum(array_map(
                fn($v) => ($v['precio'] ?? 0) * ($v['cantidad'] ?? 1),
                $input['ventanas']
            ));

            $cotizacion = Cotizacion::create([
                'cliente_id'           => $input['cliente_id'],
                'vendedor_id'          => $user->id,
                'fecha'                => now()->toDateString(),
                'estado_cotizacion_id' => $estado?->id,
                'observaciones'        => $input['observaciones'] ?? null,
                'total'                => $total,
            ]);

            foreach ($input['ventanas'] as $v) {
                Ventana::create([
                    'cotizacion_id'                => $cotizacion->id,
                    'tipo_ventana_id'              => $v['tipo_ventana_id'],
                    'ancho'                        => $v['ancho'],
                    'alto'                         => $v['alto'],
                    'color_id'                     => $v['color_id'],
                    'producto_vidrio_proveedor_id' => $v['producto_vidrio_proveedor_id'],
                    'precio'                       => $v['precio'],
                    'costo'                        => $v['costo'],
                    'cantidad'                     => $v['cantidad'],
                    'hojas_totales'                => $v['hojas_totales'] ?? null,
                    'hojas_moviles'                => $v['hojas_moviles'] ?? null,
                    'config'                       => [
                        'tipo_vidrio' => $v['tipo_vidrio'],
                        'manillon'    => $v['manillon'] ?? false,
                    ],
                ]);
            }

            DB::commit();
            return ['success' => true, 'cotizacion_id' => $cotizacion->id, 'total' => $total];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getSystemPrompt(): string
    {
        return <<<PROMPT
Eres el asistente cotizador de Vialum, empresa chilena fabricante de ventanas de aluminio y PVC.
Tu objetivo es crear cotizaciones a partir de lo que el usuario describe.

FLUJO OBLIGATORIO:
1. Identifica al cliente usando buscar_cliente. Si no existe, créalo con crear_cliente.
2. Para cada ventana llama SIEMPRE primero a listar_tipos_ventana, listar_colores y listar_vidrios para obtener los IDs exactos disponibles.
3. Si el usuario da medidas en cm, conviértelas multiplicando × 10.
4. Calcula el precio con calcular_precio_ventana.
5. Si el precio sale 0 o hay error: informa qué combinación falló y muestra las opciones disponibles (vidrios y colores que sí existen) para que el usuario elija.
6. Presenta resumen con ventanas, medidas, precios unitarios y total.
7. Pide confirmación explícita antes de crear.
8. Solo tras confirmación, llama a crear_cotizacion.

CONCEPTOS CLAVE — MUY IMPORTANTE:
- El COLOR es del marco de aluminio o PVC (ej: Blanco, Grafito, Roble, Negro). NO es del vidrio.
- El VIDRIO no tiene color. Se selecciona por nombre/tipo: Monolítico (tipo=1) o Termopanel (tipo=2).
- Cuando el usuario dice "color Roble con termopanel" → color_id = ID de Roble, vidrio = termopanel disponible.
- Nunca mezcles el color del marco con el tipo de vidrio al explicar al usuario.

MANEJO DE DATOS FALTANTES:
- Si calcular_precio_ventana devuelve costo_unitario=0: lee el campo "diagnostico" que viene en la respuesta.
  - Si el diagnóstico dice "solo hay que cambiar el color del marco": di exactamente eso al usuario, muestra los colores disponibles (listar_colores) y pregunta cuál prefiere. NO menciones el vidrio.
  - Si el diagnóstico dice "Verifica que el tipo de ventana...": informa que esa combinación no está en el sistema y ofrece alternativas de tipo de ventana.
  - El vidrio NO tiene color — nunca sugieras cambiar el vidrio cuando el problema es el color del marco.
- Si buscar_cliente no encuentra al cliente, pregunta nombre completo, email y teléfono antes de crearlo.
- Si el tipo de ventana no existe exactamente, muestra la lista y pregunta cuál es la más parecida.

REGLAS:
- Habla en español, sé conciso y directo.
- Nunca inventes IDs — usa siempre los que devuelvan las tools.
- Ventanas correderas: hojas_totales=2, hojas_moviles=1 por defecto.
- Ventanas fijas/proyectantes: hojas_totales=0, hojas_moviles=0.
- Los precios son netos sin IVA. Formato: puntos de miles (ej: $125.000).
- Cuando presentes opciones, usa listas numeradas para que el usuario pueda responder "el 2" o "el primero".
PROMPT;
    }

    private function getTools(): array
    {
        return [
            [
                'name'         => 'buscar_cliente',
                'description'  => 'Busca clientes existentes por nombre, RUT, email o razón social.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => ['q' => ['type' => 'string', 'description' => 'Término de búsqueda']],
                    'required'   => ['q'],
                ],
            ],
            [
                'name'         => 'crear_cliente',
                'description'  => 'Crea un nuevo cliente cuando no se encuentra en búsqueda.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'nombre'       => ['type' => 'string'],
                        'apellido'     => ['type' => 'string'],
                        'razon_social' => ['type' => 'string', 'description' => 'Para empresas'],
                        'email'        => ['type' => 'string'],
                        'telefono'     => ['type' => 'string'],
                        'rut'          => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name'         => 'listar_tipos_ventana',
                'description'  => 'Lista todos los tipos de ventana disponibles con sus IDs.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'         => 'listar_colores',
                'description'  => 'Lista todos los colores de aluminio disponibles con sus IDs.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'         => 'listar_vidrios',
                'description'  => 'Lista los vidrios disponibles. Retorna pcp_id, producto_id, proveedor_id y nombre.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'         => 'calcular_precio_ventana',
                'description'  => 'Calcula el costo y precio de venta de una ventana.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'tipo_ventana_id'    => ['type' => 'integer'],
                        'ancho'              => ['type' => 'number', 'description' => 'en mm'],
                        'alto'               => ['type' => 'number', 'description' => 'en mm'],
                        'color_id'           => ['type' => 'integer'],
                        'cantidad'           => ['type' => 'integer'],
                        'producto_vidrio_id' => ['type' => 'integer', 'description' => 'producto_id del vidrio'],
                        'proveedor_vidrio_id'=> ['type' => 'integer'],
                        'tipo_vidrio'        => ['type' => 'integer', 'description' => '1=Monolítico 2=Termopanel'],
                        'manillon'           => ['type' => 'boolean', 'description' => 'Solo para correderas'],
                        'hojas_totales'      => ['type' => 'integer'],
                        'hojas_moviles'      => ['type' => 'integer'],
                    ],
                    'required' => ['tipo_ventana_id', 'ancho', 'alto', 'color_id', 'cantidad',
                                   'producto_vidrio_id', 'proveedor_vidrio_id', 'tipo_vidrio'],
                ],
            ],
            [
                'name'         => 'crear_cotizacion',
                'description'  => 'Crea la cotización final. Llamar SOLO tras confirmación explícita del usuario.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'cliente_id'    => ['type' => 'integer'],
                        'observaciones' => ['type' => 'string'],
                        'ventanas'      => [
                            'type'  => 'array',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'tipo_ventana_id'              => ['type' => 'integer'],
                                    'ancho'                        => ['type' => 'number'],
                                    'alto'                         => ['type' => 'number'],
                                    'color_id'                     => ['type' => 'integer'],
                                    'producto_vidrio_proveedor_id' => ['type' => 'integer', 'description' => 'pcp_id del vidrio'],
                                    'tipo_vidrio'                  => ['type' => 'integer'],
                                    'manillon'                     => ['type' => 'boolean'],
                                    'hojas_totales'                => ['type' => 'integer'],
                                    'hojas_moviles'                => ['type' => 'integer'],
                                    'cantidad'                     => ['type' => 'integer'],
                                    'precio'                       => ['type' => 'number'],
                                    'costo'                        => ['type' => 'number'],
                                ],
                                'required' => ['tipo_ventana_id', 'ancho', 'alto', 'color_id',
                                               'producto_vidrio_proveedor_id', 'tipo_vidrio',
                                               'cantidad', 'precio', 'costo'],
                            ],
                        ],
                    ],
                    'required' => ['cliente_id', 'ventanas'],
                ],
            ],
        ];
    }
}
