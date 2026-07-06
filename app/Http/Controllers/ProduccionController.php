<?php

namespace App\Http\Controllers;

use App\Services\CortesService;
use App\Models\Cotizacion;
use App\Models\Color;
use App\Models\Empleado;
use App\Models\EtapaProduccion;
use App\Models\Producto;
use App\Services\CalculoVentanaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProduccionController extends Controller
{
    public function hojaCortes(int $id)
    {
        $data = CortesService::generarHojaCortes($id);
        return response()->json($data);
    }

    public function resumenMateriales(int $id)
    {
        $cotizacion = Cotizacion::with([
            'ventanas.tipoVentana',
            'ventanas.color',
            'ventanas.productoVidrioProveedor.producto',
            'ventanas.productoVidrioProveedor.proveedor',
            'ventanas.productoVidrioProveedor.color',
            'cliente',
        ])->findOrFail($id);

        $agregado = []; // clave: producto_id + '_' + color_id

        foreach ($cotizacion->ventanas as $ventana) {
            $cpp    = $ventana->productoVidrioProveedor;
            $config = $ventana->config ?? [];

            // Reconstruir el array que espera calcularMateriales
            $ventanaArray = [
                'tipo'             => $ventana->tipo_ventana_id,
                'ancho'            => (float) $ventana->ancho,
                'alto'             => (float) $ventana->alto,
                'color'            => $ventana->color_id,
                'cantidad'         => max(1, (int) $ventana->cantidad),
                'productoVidrio'   => $cpp?->producto_id,
                'proveedorVidrio'  => $cpp?->proveedor_id ?? $config['proveedor_vidrio'] ?? null,
                'tipoVidrio'       => $config['tipo_vidrio'] ?? 2,
                'manillon'         => $config['manillon'] ?? false,
                'hojas_totales'    => $ventana->hojas_totales,
                'hojas_moviles'    => $ventana->hojas_moviles,
            ];

            try {
                $resultado = CalculoVentanaService::calcularMateriales($ventanaArray);
            } catch (\Throwable $e) {
                continue; // tipo no soportado o datos insuficientes
            }

            $ventanaColorId = $ventanaArray['color'];

            foreach ($resultado['materiales'] ?? [] as $mat) {
                if (empty($mat['producto_id'])) continue;

                // Inject the window's color into every material (service doesn't return it)
                $colorId = $mat['color_id'] ?? $ventanaColorId;
                $key     = $mat['producto_id'] . '_' . $colorId;

                if (!isset($agregado[$key])) {
                    $agregado[$key] = [
                        'producto_id'   => $mat['producto_id'],
                        'nombre'        => $mat['nombre'] ?? '',
                        'color_id'      => $colorId,
                        'color'         => '',          // filled after loop
                        'proveedor'     => $mat['proveedor'] ?? '',
                        'unidad'        => $mat['unidad'] ?? '',
                        'cantidad'      => 0,
                        'costo_total'   => 0,
                    ];
                }

                $agregado[$key]['cantidad']    += (float) ($mat['cantidad'] ?? 0);
                $agregado[$key]['costo_total'] += (float) ($mat['costo_total'] ?? 0);
            }
        }

        // Enriquecer con datos del modelo Producto (largo_total, tipo_producto, unidad) y Color
        $lista = array_values($agregado);
        $productoIds = array_column($lista, 'producto_id');
        $colorIds    = array_unique(array_filter(array_column($lista, 'color_id')));

        $productos = Producto::with(['tipoProducto', 'unidad'])
            ->whereIn('id', $productoIds)
            ->get()
            ->keyBy('id');

        $colores = Color::whereIn('id', $colorIds)->get()->keyBy('id');

        foreach ($lista as &$item) {
            $p = $productos[$item['producto_id']] ?? null;
            $item['tipo_producto'] = $p?->tipoProducto?->nombre ?? '';
            $item['largo_total_m'] = $p ? (float) $p->largo_total : null;
            $item['unidad_nombre'] = $p?->unidad?->abreviacion ?? $item['unidad'];
            $item['color']         = $colores[$item['color_id']]?->nombre ?? '';

            // Calcular barras para perfiles (unidad 'm')
            if ($item['unidad'] === 'm' && $item['largo_total_m'] > 0) {
                $item['barras'] = (int) ceil($item['cantidad'] / $item['largo_total_m']);
            } else {
                $item['barras'] = null;
            }
        }
        unset($item);

        // Ordenar: primero perfiles/barras, luego herrajes, luego vidrios
        usort($lista, function ($a, $b) {
            $orden = ['Perfil' => 0, 'Barra' => 0, 'Tira' => 0, 'Herraje' => 1, 'Vidrio' => 2];
            $ta = $orden[$a['tipo_producto']] ?? 3;
            $tb = $orden[$b['tipo_producto']] ?? 3;
            return $ta !== $tb ? $ta - $tb : strcmp($a['nombre'], $b['nombre']);
        });

        return response()->json([
            'cotizacion' => [
                'id'      => $cotizacion->id,
                'cliente' => $cotizacion->cliente?->razon_social
                             ?? trim(($cotizacion->cliente?->first_name ?? '') . ' ' . ($cotizacion->cliente?->last_name ?? '')),
                'fecha'   => $cotizacion->fecha,
            ],
            'materiales' => $lista,
        ]);
    }

    // ── Vista de Taller ───────────────────────────────────────────────────────

    public function taller(): JsonResponse
    {
        $cotizaciones = Cotizacion::with(['cliente', 'ventanas', 'etapas.empleado'])
            ->whereIn('estado_produccion', ['Lista para Corte', 'En Fabricación', 'Fabricadas OK'])
            ->orderByRaw("FIELD(estado_produccion, 'En Fabricación', 'Lista para Corte', 'Fabricadas OK')")
            ->orderBy('fecha_entrega')
            ->get();

        $empleados = Empleado::where('activo', true)->select('id', 'nombre')->get();

        $data = $cotizaciones->map(function ($c) {
            $cliente = $c->cliente?->razon_social
                ?? trim(($c->cliente?->first_name ?? '') . ' ' . ($c->cliente?->last_name ?? ''));

            $m2 = \DB::table('ventanas')
                ->where('cotizacion_id', $c->id)
                ->selectRaw('COALESCE(SUM((ancho/1000.0)*(alto/1000.0)*cantidad),0) as total')
                ->value('total');

            $etapasIndexadas = $c->etapas->keyBy('etapa');

            $etapasDisponibles = $this->etapasParaCotizacion($c);

            $etapas = collect($etapasDisponibles)->map(function ($etapaKey) use ($etapasIndexadas) {
                $registro = $etapasIndexadas->get($etapaKey);
                return [
                    'etapa'        => $etapaKey,
                    'label'        => $this->etapaLabel($etapaKey),
                    'estado'       => $registro?->estado ?? 'pendiente',
                    'empleado_id'  => $registro?->empleado_id,
                    'empleado'     => $registro?->empleado?->nombre,
                    'fecha_inicio' => $registro?->fecha_inicio,
                    'fecha_fin_real' => $registro?->fecha_fin_real,
                    'notas'        => $registro?->notas,
                    'id'           => $registro?->id,
                ];
            })->values();

            $diasParaEntrega = null;
            $urgencia = 'normal';
            if ($c->fecha_entrega) {
                $diasParaEntrega = (int) Carbon::today()->diffInDays(Carbon::parse($c->fecha_entrega), false);
                if ($diasParaEntrega < 0)       $urgencia = 'vencida';
                elseif ($diasParaEntrega <= 2)  $urgencia = 'critica';
                elseif ($diasParaEntrega <= 5)  $urgencia = 'proxima';
            }

            $etapasCompletadas = $etapas->where('estado', 'completado')->count();
            $totalEtapas       = $etapas->count();
            $progreso          = $totalEtapas > 0 ? round($etapasCompletadas / $totalEtapas * 100) : 0;

            return [
                'id'               => $c->id,
                'cliente'          => $cliente,
                'estado_produccion' => $c->estado_produccion,
                'fecha_entrega'    => $c->fecha_entrega,
                'dias_para_entrega' => $diasParaEntrega,
                'urgencia'         => $urgencia,
                'm2'               => round((float) $m2, 2),
                'cant_ventanas'    => $c->ventanas->sum('cantidad'),
                'winperfil_numero' => $c->winperfil_numero,
                'winperfil_serie'  => $c->winperfil_serie,
                'fabricar_termopanel' => (bool) $c->fabricar_termopanel,
                'cortar_vidrio_cnc'   => (bool) $c->cortar_vidrio_cnc,
                'notas_operaciones' => $c->notas_operaciones,
                'etapas'           => $etapas,
                'progreso'         => $progreso,
            ];
        });

        return response()->json([
            'cotizaciones' => $data,
            'empleados'    => $empleados,
            'stats' => [
                'en_fabricacion' => $cotizaciones->where('estado_produccion', 'En Fabricación')->count(),
                'lista_corte'    => $cotizaciones->where('estado_produccion', 'Lista para Corte')->count(),
                'fabricadas_ok'  => $cotizaciones->where('estado_produccion', 'Fabricadas OK')->count(),
            ],
        ]);
    }

    public function guardarEtapa(Request $request, int $cotizacionId): JsonResponse
    {
        $request->validate([
            'etapa'       => 'required|string',
            'estado'      => 'required|in:pendiente,en_progreso,completado',
            'empleado_id' => 'nullable|integer|exists:empleados,id',
            'notas'       => 'nullable|string|max:500',
        ]);

        $datos = [
            'estado'      => $request->estado,
            'empleado_id' => $request->empleado_id,
            'notas'       => $request->notas,
        ];

        if ($request->estado === 'en_progreso') {
            $datos['fecha_inicio'] = now()->toDateString();
        } elseif ($request->estado === 'completado') {
            $datos['fecha_fin_real'] = now()->toDateString();
        }

        $etapa = EtapaProduccion::updateOrCreate(
            ['cotizacion_id' => $cotizacionId, 'etapa' => $request->etapa],
            $datos
        );

        // Actualizar estado_produccion de la cotización automáticamente
        $this->actualizarEstadoCotizacion($cotizacionId);

        return response()->json(['ok' => true, 'etapa' => $etapa]);
    }

    private function actualizarEstadoCotizacion(int $cotizacionId): void
    {
        $cotizacion = Cotizacion::find($cotizacionId);
        if (!$cotizacion) return;

        $etapas = EtapaProduccion::where('cotizacion_id', $cotizacionId)->get();
        if ($etapas->isEmpty()) return;

        $hayEnProgreso  = $etapas->contains('estado', 'en_progreso');
        $todasCompletas = $etapas->every(fn($e) => $e->estado === 'completado');

        if ($todasCompletas) {
            $cotizacion->update(['estado_produccion' => 'Fabricadas OK']);
        } elseif ($hayEnProgreso || $etapas->contains('estado', 'completado')) {
            $cotizacion->update(['estado_produccion' => 'En Fabricación']);
        }
    }

    private function etapasParaCotizacion(Cotizacion $c): array
    {
        $etapas = ['corte_perfiles', 'armado', 'vidriado', 'junquillos', 'control'];

        if ($c->cortar_vidrio_cnc) {
            array_splice($etapas, 1, 0, ['corte_vidrio']);
        }
        if ($c->fabricar_termopanel) {
            array_splice($etapas, array_search('armado', $etapas), 0, ['fabricacion_termopanel']);
        }

        $etapas[] = 'instalacion';
        $etapas[] = 'entrega';

        return $etapas;
    }

    private function etapaLabel(string $etapa): string
    {
        return match ($etapa) {
            'corte_perfiles'       => 'Corte Perfiles',
            'corte_vidrio'         => 'Corte Vidrio CNC',
            'fabricacion_termopanel' => 'Termopanel',
            'armado'               => 'Armado',
            'vidriado'             => 'Vidriado',
            'junquillos'           => 'Junquillos',
            'control'              => 'Control Calidad',
            'instalacion'          => 'Instalación',
            'entrega'              => 'Entrega',
            default                => ucfirst(str_replace('_', ' ', $etapa)),
        };
    }
}
