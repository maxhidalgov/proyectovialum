<?php

namespace App\Http\Controllers;

use App\Services\CortesService;
use App\Models\Cotizacion;
use App\Models\Color;
use App\Models\Producto;
use App\Services\CalculoVentanaService;
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
}
