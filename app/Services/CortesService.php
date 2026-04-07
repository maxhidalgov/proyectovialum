<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\Ventana;
use App\Models\Producto;
use App\Models\ProductoColorProveedor;

class CortesService
{
    /**
     * Kerf (virutas) en mm por corte de sierra.
     * Ajustar según la máquina de corte.
     */
    const KERF_MM = 4;

    /**
     * Genera la hoja de cortes completa para una cotización.
     *
     * Retorna:
     * [
     *   'cotizacion' => [...],
     *   'grupos'     => [          // agrupado por producto_id + color_id
     *     [
     *       'producto_id'  => int,
     *       'nombre'       => string,
     *       'proveedor'    => string,
     *       'color'        => string,
     *       'largo_barra'  => int,   // mm (largo_total del producto × 1000)
     *       'barras'       => [      // resultado del algoritmo FFD
     *         [
     *           'numero'   => int,
     *           'cortes'   => [
     *             ['largo_mm'=>int, 'ventana_ref'=>string, 'posicion'=>string,
     *              'angulo_izq'=>int, 'angulo_der'=>int]
     *           ],
     *           'uso_mm'   => int,
     *           'retal_mm' => int,
     *           'virutas_mm'=> int,
     *         ]
     *       ],
     *       'total_barras' => int,
     *     ]
     *   ]
     * ]
     */
    public static function generarHojaCortes(int $cotizacionId): array
    {
        $cotizacion = Cotizacion::with([
            'ventanas.tipoVentana',
            'ventanas.color',
            'ventanas.productoVidrioProveedor.producto',
            'ventanas.productoVidrioProveedor.proveedor',
            'cliente',
        ])->findOrFail($cotizacionId);

        // 1. Recolectar todos los cortes individuales de cada ventana
        $todosLosCortes = [];
        $ventanasOmitidas = [];

        $tiposNombre = [
            1  => 'Fija AL42',
            2  => 'Fija S60',
            3  => 'Corredera Sliding E15',
            45 => 'Proyectante S60',
            46 => 'Corredera Andes',
            47 => 'Bay Window',
            49 => 'Abatir S60',
            50 => 'Puerta S60',
            51 => 'Puerta 2 Hojas S60',
            52 => 'Corredera Sliding 98',
            53 => 'Corredera Monorriel',
            55 => 'Corredera AL25',
            56 => 'Proyectante AL42',
            57 => 'Compuesta AL42',
            58 => 'Universal',
        ];

        foreach ($cotizacion->ventanas as $idx => $ventana) {
            $ventanaRef = 'V' . ($idx + 1);
            $cortes = self::extraerCortes($ventana, $ventanaRef);
            if (empty($cortes)) {
                $tipoId   = $ventana->tipo_ventana_id;
                $ventanasOmitidas[] = [
                    'ref'    => $ventanaRef,
                    'tipo'   => $tiposNombre[$tipoId] ?? $ventana->tipoVentana?->nombre ?? "Tipo ID $tipoId",
                    'ancho'  => $ventana->ancho,
                    'alto'   => $ventana->alto,
                ];
            }
            $todosLosCortes = array_merge($todosLosCortes, $cortes);
        }

        if (empty($todosLosCortes)) {
            return [
                'cotizacion'       => self::infoCotizacion($cotizacion),
                'grupos'           => [],
                'ventanas_omitidas'=> $ventanasOmitidas,
            ];
        }

        // 2. Agrupar por producto_id + color_id
        $grupos = [];
        foreach ($todosLosCortes as $corte) {
            $key = $corte['producto_id'] . '_' . $corte['color_id'];
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'producto_id' => $corte['producto_id'],
                    'nombre'      => $corte['nombre'],
                    'proveedor'   => $corte['proveedor'],
                    'color'       => $corte['color'],
                    'color_id'    => $corte['color_id'],
                    'largo_barra' => $corte['largo_barra'],
                    'cortes'      => [],
                ];
            }
            $grupos[$key]['cortes'][] = $corte;
        }

        // 3. Ejecutar FFD (First Fit Decreasing) en cada grupo
        $resultado = [];
        foreach ($grupos as $grupo) {
            $barras = self::ffd($grupo['cortes'], $grupo['largo_barra']);
            $resultado[] = [
                'producto_id'  => $grupo['producto_id'],
                'nombre'       => $grupo['nombre'],
                'proveedor'    => $grupo['proveedor'],
                'color'        => $grupo['color'],
                'largo_barra'  => $grupo['largo_barra'],
                'barras'       => $barras,
                'total_barras' => count($barras),
                'total_cortes' => count($grupo['cortes']),
            ];
        }

        // Ordenar por nombre de perfil para que la hoja sea legible
        usort($resultado, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return [
            'cotizacion'        => self::infoCotizacion($cotizacion),
            'grupos'            => $resultado,
            'ventanas_omitidas' => $ventanasOmitidas,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // ALGORITMO FFD (First Fit Decreasing)
    // ─────────────────────────────────────────────────────────────
    private static function ffd(array $cortes, int $largoBarra): array
    {
        // Ordenar de mayor a menor
        usort($cortes, fn($a, $b) => $b['largo_mm'] - $a['largo_mm']);

        $barras = [];

        foreach ($cortes as $corte) {
            $colocado = false;
            foreach ($barras as &$barra) {
                $usoActual = array_sum(array_column($barra['cortes'], 'largo_mm'))
                           + count($barra['cortes']) * self::KERF_MM;
                $disponible = $largoBarra - $usoActual - self::KERF_MM;

                if ($corte['largo_mm'] <= $disponible) {
                    $barra['cortes'][] = $corte;
                    $colocado = true;
                    break;
                }
            }
            unset($barra);

            if (!$colocado) {
                $barras[] = ['numero' => count($barras) + 1, 'cortes' => [$corte]];
            }
        }

        // Calcular métricas por barra
        foreach ($barras as &$barra) {
            $usoMm     = array_sum(array_column($barra['cortes'], 'largo_mm'));
            $virasMm   = count($barra['cortes']) * self::KERF_MM;
            $retalMm   = $largoBarra - $usoMm - $virasMm;
            $barra['uso_mm']    = $usoMm;
            $barra['virutas_mm']= $virasMm;
            $barra['retal_mm']  = max(0, $retalMm);
        }
        unset($barra);

        return $barras;
    }

    // ─────────────────────────────────────────────────────────────
    // EXTRAER CORTES DE UNA VENTANA
    // ─────────────────────────────────────────────────────────────
    private static function extraerCortes(Ventana $ventana, string $ref): array
    {
        $ancho    = (int) $ventana->ancho;
        $alto     = (int) $ventana->alto;
        $colorId  = $ventana->color_id;
        $cantidad = max(1, (int) ($ventana->cantidad ?? 1));
        $config   = $ventana->config ?? [];
        $tipoVidrio  = $config['tipo_vidrio'] ?? 2;
        $manillon    = $config['manillon'] ?? false;
        $hojasTotales = (int) ($ventana->hojas_totales ?? 2);
        $hojasMoviles = (int) ($ventana->hojas_moviles ?? 2);

        // Determinar tipo_ventana_id
        $tipoId = $ventana->tipo_ventana_id;

        // Construir lista de cortes según el tipo de ventana
        $cortesConfig = self::getCortesConfig($tipoId, $ancho, $alto, $tipoVidrio, $manillon, $hojasTotales, $hojasMoviles);

        if (empty($cortesConfig)) {
            return [];
        }

        // Cargar productos para obtener largo_total, nombre, proveedor
        $productoIds = array_unique(array_column($cortesConfig, 'producto_id'));
        $productos = Producto::with('coloresPorProveedor.proveedor')
            ->whereIn('id', $productoIds)
            ->get()
            ->keyBy('id');

        $cortes = [];
        foreach ($cortesConfig as $c) {
            $producto = $productos[$c['producto_id']] ?? null;
            if (!$producto) continue;

            $largoBarra = (int) round(($producto->largo_total ?? 6) * 1000); // metros → mm
            // Buscar proveedor para este color
            $cpp = $producto->coloresPorProveedor->first(fn($r) => $r->color_id == $colorId);
            $proveedor = $cpp?->proveedor?->nombre ?? 'N/A';
            $colorNombre = $cpp?->color?->nombre ?? $ventana->color?->nombre ?? '';

            // Expandir por cantidad de ventanas
            for ($q = 0; $q < $cantidad; $q++) {
                $ventanaRefQ = $cantidad > 1 ? "{$ref}." . ($q + 1) : $ref;
                for ($i = 0; $i < $c['cant']; $i++) {
                    $cortes[] = [
                        'producto_id' => $producto->id,
                        'nombre'      => $producto->nombre,
                        'proveedor'   => $proveedor,
                        'color'       => $colorNombre,
                        'color_id'    => $colorId,
                        'largo_barra' => $largoBarra,
                        'largo_mm'    => (int) round($c['largo_mm']),
                        'angulo_izq'  => $c['angulo_izq'] ?? 90,
                        'angulo_der'  => $c['angulo_der'] ?? 90,
                        'posicion'    => $c['posicion'],
                        'ventana_ref' => $ventanaRefQ,
                    ];
                }
            }
        }

        return $cortes;
    }

    // ─────────────────────────────────────────────────────────────
    // CONFIGURACIÓN DE CORTES POR TIPO DE VENTANA
    // Retorna array de: [producto_id, largo_mm, cant, posicion, angulo_izq, angulo_der]
    // ─────────────────────────────────────────────────────────────
    private static function getCortesConfig(
        int $tipoId, int $x, int $y,
        int $tipoVidrio, bool $manillon,
        int $hojasTotales, int $hojasMoviles
    ): array {

        // ─── Junquillos AL42 ───
        $jqAL42 = $tipoVidrio == 1 ? 151 : 153;

        switch ($tipoId) {

            // ── Fija AL42 ──────────────────────────────────────────
            case 1:
                return [
                    ['producto_id'=>148,'largo_mm'=>$x,       'cant'=>1,'posicion'=>'Horizontal superior','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>152,'largo_mm'=>$x+40,    'cant'=>1,'posicion'=>'Horizontal inferior (cámara agua)','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>148,'largo_mm'=>$y-20,    'cant'=>2,'posicion'=>'Vertical','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>$jqAL42,'largo_mm'=>$x-25.6,  'cant'=>2,'posicion'=>'Junquillo horizontal','angulo_izq'=>45,'angulo_der'=>45],
                    ['producto_id'=>$jqAL42,'largo_mm'=>$y-45.61, 'cant'=>2,'posicion'=>'Junquillo vertical','angulo_izq'=>45,'angulo_der'=>45],
                ];

            // ── Proyectante AL42 ───────────────────────────────────
            case 56:
                return [
                    ['producto_id'=>148,'largo_mm'=>$x,      'cant'=>1,'posicion'=>'Marco superior','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>152,'largo_mm'=>$x+40,   'cant'=>1,'posicion'=>'Marco inferior (cámara agua)','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>148,'largo_mm'=>$y-20,   'cant'=>2,'posicion'=>'Jamba','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>150,'largo_mm'=>$x-18,   'cant'=>2,'posicion'=>'Hoja horizontal','angulo_izq'=>45,'angulo_der'=>45],
                    ['producto_id'=>150,'largo_mm'=>$y-38,   'cant'=>2,'posicion'=>'Pierna','angulo_izq'=>45,'angulo_der'=>45],
                    ['producto_id'=>$jqAL42,'largo_mm'=>$x-90,  'cant'=>2,'posicion'=>'Junquillo horizontal','angulo_izq'=>45,'angulo_der'=>45],
                    ['producto_id'=>$jqAL42,'largo_mm'=>$y-110, 'cant'=>2,'posicion'=>'Junquillo vertical','angulo_izq'=>45,'angulo_der'=>45],
                ];

            // ── Fija S60 ───────────────────────────────────────────
            case 2:
                $jq = $tipoVidrio == 1 ? 45 : 35;
                return [
                    ['producto_id'=>32,'largo_mm'=>$x,'cant'=>2,'posicion'=>'Marco horizontal','angulo_izq'=>45,'angulo_der'=>45],
                    ['producto_id'=>32,'largo_mm'=>$y,'cant'=>2,'posicion'=>'Marco vertical','angulo_izq'=>45,'angulo_der'=>45],
                    ['producto_id'=>34,'largo_mm'=>$x,'cant'=>2,'posicion'=>'Refuerzo horizontal','angulo_izq'=>45,'angulo_der'=>45],
                    ['producto_id'=>34,'largo_mm'=>$y,'cant'=>2,'posicion'=>'Refuerzo vertical','angulo_izq'=>45,'angulo_der'=>45],
                    ['producto_id'=>$jq,'largo_mm'=>$x,'cant'=>2,'posicion'=>'Junquillo horizontal','angulo_izq'=>45,'angulo_der'=>45],
                    ['producto_id'=>$jq,'largo_mm'=>$y,'cant'=>2,'posicion'=>'Junquillo vertical','angulo_izq'=>45,'angulo_der'=>45],
                ];

            // ── Corredera AL25 ─────────────────────────────────────
            case 55:
                if ($tipoVidrio == 2 && $manillon) {
                    return [
                        ['producto_id'=>154,'largo_mm'=>$x-16,          'cant'=>1,'posicion'=>'Riel superior','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>157,'largo_mm'=>$x-16,          'cant'=>1,'posicion'=>'Riel inferior','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>160,'largo_mm'=>$y,             'cant'=>2,'posicion'=>'Jamba','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>164,'largo_mm'=>$x/2+3,         'cant'=>2,'posicion'=>'Zócalo TP (hoja)','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>166,'largo_mm'=>$y-58,          'cant'=>2,'posicion'=>'Pierna abierta TP manillón','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>165,'largo_mm'=>$x/2-65,        'cant'=>4,'posicion'=>'Traslapo TP','angulo_izq'=>90,'angulo_der'=>90],
                    ];
                } elseif ($tipoVidrio == 2 && !$manillon) {
                    return [
                        ['producto_id'=>154,'largo_mm'=>$x-16,  'cant'=>1,'posicion'=>'Riel superior','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>157,'largo_mm'=>$x-16,  'cant'=>1,'posicion'=>'Riel inferior','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>160,'largo_mm'=>$y,     'cant'=>2,'posicion'=>'Jamba','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>164,'largo_mm'=>$x/2+3, 'cant'=>2,'posicion'=>'Zócalo TP (hoja)','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>176,'largo_mm'=>$y-58,  'cant'=>2,'posicion'=>'Pierna TP pestillo','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>165,'largo_mm'=>$x/2-65,'cant'=>4,'posicion'=>'Traslapo TP','angulo_izq'=>90,'angulo_der'=>90],
                    ];
                } elseif ($tipoVidrio == 1 && $manillon) {
                    return [
                        ['producto_id'=>154,'largo_mm'=>$x-16,  'cant'=>1,'posicion'=>'Riel superior','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>157,'largo_mm'=>$x-16,  'cant'=>1,'posicion'=>'Riel inferior','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>160,'largo_mm'=>$y,     'cant'=>2,'posicion'=>'Jamba','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>155,'largo_mm'=>$x/2+3, 'cant'=>1,'posicion'=>'Cabezal (hoja sup)','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>156,'largo_mm'=>$x/2+3, 'cant'=>2,'posicion'=>'Zócalo (hoja lat)','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>161,'largo_mm'=>$y-58,  'cant'=>2,'posicion'=>'Pierna abierta manillón','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>158,'largo_mm'=>$x/2-63,'cant'=>2,'posicion'=>'Traslapo','angulo_izq'=>90,'angulo_der'=>90],
                    ];
                } else {
                    return [
                        ['producto_id'=>154,'largo_mm'=>$x-16,  'cant'=>1,'posicion'=>'Riel superior','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>157,'largo_mm'=>$x-16,  'cant'=>1,'posicion'=>'Riel inferior','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>160,'largo_mm'=>$y,     'cant'=>2,'posicion'=>'Jamba','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>155,'largo_mm'=>$x/2+3, 'cant'=>1,'posicion'=>'Cabezal (hoja sup)','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>156,'largo_mm'=>$x/2+3, 'cant'=>2,'posicion'=>'Zócalo (hoja lat)','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>159,'largo_mm'=>$y-58,  'cant'=>2,'posicion'=>'Pierna pestillo','angulo_izq'=>90,'angulo_der'=>90],
                        ['producto_id'=>158,'largo_mm'=>$x/2-63,'cant'=>2,'posicion'=>'Traslapo','angulo_izq'=>90,'angulo_der'=>90],
                    ];
                }

            // ── Corredera Sliding S60 (tipo 3) ─────────────────────
            case 3:
                return [
                    ['producto_id'=>46,'largo_mm'=>$x+5,                         'cant'=>2,'posicion'=>'Marco horizontal','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>46,'largo_mm'=>$y+5,                         'cant'=>2,'posicion'=>'Marco vertical','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>47,'largo_mm'=>$x-80,                        'cant'=>2,'posicion'=>'Refuerzo horizontal','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>47,'largo_mm'=>$y-80,                        'cant'=>2,'posicion'=>'Refuerzo vertical','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>48,'largo_mm'=>$y-54-54+16+5,                'cant'=>$hojasTotales*2,'posicion'=>'Perfil hoja vertical','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>48,'largo_mm'=>(($x-92)/2)+45,              'cant'=>$hojasTotales*2,'posicion'=>'Perfil hoja horizontal','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>49,'largo_mm'=>$y-54-54+16+5-62-20,          'cant'=>$hojasTotales*2,'posicion'=>'Refuerzo hoja vertical','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>49,'largo_mm'=>((($x-92)/2)+45-62-25),       'cant'=>$hojasTotales*2,'posicion'=>'Refuerzo hoja horizontal','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>50,'largo_mm'=>$y-54-54+16+5-7,              'cant'=>$hojasTotales,'posicion'=>'Pieza central','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>51,'largo_mm'=>$x-54-54-1,                   'cant'=>2,'posicion'=>'Umbral','angulo_izq'=>90,'angulo_der'=>90],
                    ['producto_id'=>52,'largo_mm'=>(($x-92)/2)+45-62-62-5,       'cant'=>$hojasTotales*2,'posicion'=>'Junquillo hoja horiz','angulo_izq'=>45,'angulo_der'=>45],
                    ['producto_id'=>52,'largo_mm'=>$y-54-54+16+5-62-62-5,        'cant'=>$hojasTotales*2,'posicion'=>'Junquillo hoja vert','angulo_izq'=>45,'angulo_der'=>45],
                ];

            default:
                return [];
        }
    }

    // ─────────────────────────────────────────────────────────────
    private static function infoCotizacion(Cotizacion $c): array
    {
        return [
            'id'      => $c->id,
            'cliente' => $c->cliente?->nombre ?? 'Sin cliente',
            'fecha'   => $c->fecha,
        ];
    }
}
