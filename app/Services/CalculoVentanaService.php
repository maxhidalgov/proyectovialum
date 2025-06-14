<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\Log;
use App\Models\ProductoColorProveedor;

class CalculoVentanaService
{
    public static function calcularMateriales(array $ventana): array
    {
        $tipoVentanaId = $ventana['tipo'] ?? null;

        if ($tipoVentanaId == 2) {
            return self::calcularFijaS60($ventana);
        }

        if ($tipoVentanaId == 3) {
            return self::calcularCorrederaSliding($ventana);
        }

        if ($tipoVentanaId == 45) {
            return self::calcularProyectanteS60($ventana);
        }

        if ($tipoVentanaId == 46) {
            return self::calcularCorrederaAndes($ventana);
        }

        return [
            'materiales' => [],
            'costo_total' => 0,
        ];
    }

    protected static function calcularFijaS60(array $ventana): array
    {
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $productoVidrioId = $ventana['productoVidrio'];
        $colorId = $ventana['color'];
        $proveedorVidrio = $ventana['proveedorVidrio'];
        $tipoVidrioId = $ventana['tipoVidrio'] ?? null;
        $cantidad = $ventana['cantidad'] ?? 1;

        $idMarco = 32;
        $idRefuerzo = 34;
        $idJunquillo = match ($tipoVidrioId) {
            1 => 45, // Monolítico
            2 => 35, // Termopanel
            default => 35,
        };
        $idPuente = 36;
        $idCalzoAmarillo = 37;
        $idCalzoCeleste = 38;
        $idCalzoRojo = 39;
        $idTornilloAuto = 40;
        $idTornilloAmo = 41;
        $idTapaDesague = 43;
        $idTapaTornillo = 42;
        $idSilicona = 44;

        $perfilIds = [$idMarco, $idRefuerzo, $idJunquillo, $productoVidrioId, $idPuente, $idCalzoAmarillo, $idCalzoCeleste, $idCalzoRojo, $idTornilloAuto, $idTornilloAmo, $idTapaDesague, $idTapaTornillo, $idSilicona];

        $productos = Producto::with('coloresPorProveedor.proveedor')
            ->whereIn('id', $perfilIds)
            ->get()
            ->keyBy('id');

        $materiales = [];

        $materiales[] = self::crearLinea($productos[$idMarco], 2 * $cantidad, $alto, $colorId);
        $materiales[] = self::crearLinea($productos[$idMarco], 2 * $cantidad, $ancho, $colorId);
        $materiales[] = self::crearLinea($productos[$idRefuerzo], 2 * $cantidad, $alto, $colorId);
        $materiales[] = self::crearLinea($productos[$idRefuerzo], 2 * $cantidad, $ancho, $colorId);
        $materiales[] = self::crearLinea($productos[$idJunquillo], 2 * $cantidad, $alto, $colorId);
        $materiales[] = self::crearLinea($productos[$idJunquillo], 2 * $cantidad, $ancho, $colorId);

        $materiales[] = self::crearHerraje($productos[$idPuente], $alto, $ancho, null, $colorId);
        $materiales[] = self::crearHerraje($productos[$idCalzoAmarillo], $alto, $ancho, null, $colorId);
        $materiales[] = self::crearHerraje($productos[$idCalzoCeleste], $alto, $ancho, null, $colorId);
        $materiales[] = self::crearHerraje($productos[$idCalzoRojo], $alto, $ancho, null, $colorId);

        $materiales[] = self::crearHerraje($productos[$idTornilloAuto], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[$idTornilloAmo], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[$idTapaDesague], $alto, $ancho, self::calcularCantidadTapaDesague($ancho)  * $cantidad, $colorId);
        $materiales[] = self::crearHerraje($productos[$idTapaTornillo], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[$idSilicona], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1) * $cantidad, $colorId);

        $areaM2 = ($ancho / 1000) * ($alto / 1000);
        $productoVidrio = $productos[$productoVidrioId];
        // Verifica si existe la combinación exacta
        $vidrioMatch = $productoVidrio->coloresPorProveedor->first(function ($cpp) use ($colorId, $proveedorVidrio) {
            return (int)$cpp->color_id === (int)$colorId && (int)$cpp->proveedor_id === (int)$proveedorVidrio;
        });

        // Si no existe, intenta con color_id 3
        $colorIdVidrio = $vidrioMatch ? $colorId : 3;

        // Buscar costo con color ajustado
        $costoVidrio = self::buscarCostoPorColor($productoVidrio, $colorIdVidrio, $proveedorVidrio);
        $proveedorNombreVidrio = self::buscarNombreProveedor($productoVidrio, $colorId, $proveedorVidrio);

        $matchVidrio = $productoVidrio->coloresPorProveedor
    ->first(fn($cpp) => $cpp->color_id == $colorIdVidrio && $cpp->proveedor_id == $proveedorVidrio);

    $materiales[] = [
        'producto_id' => $productoVidrio->id,
        'nombre' => $productoVidrio->nombre,
        'unidad' => 'm2',
        'cantidad' => round($areaM2, 3) * $cantidad,
        'costo_unitario' => round($costoVidrio),
        'costo_total' => round($costoVidrio * $areaM2) * $cantidad,
        'proveedor' => $matchVidrio?->proveedor?->nombre ?? 'N/A',
    ];

    $costoTotal = array_sum(array_column($materiales, 'costo_total'));

    return [
        'materiales' => $materiales,
        'costo_total' => round($costoTotal),
        'costo_unitario' => round($costoTotal / $cantidad),
    ];
    }
    // falta agregar calzos, tornillos, tapas y silicona
    protected static function calcularCorrederaSliding(array $ventana): array
    {
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $hojasTotales = $ventana['hojas_totales'] ?? 2;
        $hojasMoviles = $ventana['hojas_moviles'] ?? 2;
        $cantidad = $ventana['cantidad'] ?? 1;

        $productoIds = [46, 47, 48, 49, 50, 51, 52, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, $productoVidrioId];

        $productos = Producto::with(['coloresPorProveedor.proveedor'])
            ->whereIn('id', $productoIds)
            ->get()
            ->keyBy('id');

        $materiales = [];

        $addLinea = function ($id, $cantidad, $largoMm) use (&$materiales, $productos, $colorId) {
            $producto = $productos[$id];
            $largoMt = $largoMm / 1000;
            $costoBarra = CalculoVentanaService::buscarCostoPorColor($producto, $colorId);
            $costoPorMetro = $producto->largo_total > 0 ? $costoBarra / $producto->largo_total : 0;
            $costoTotal = $cantidad * $largoMt * $costoPorMetro;
            $materiales[] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'unidad' => 'm',
                'cantidad' => round($cantidad * $largoMt, 3),
                'costo_unitario' => round($costoPorMetro),
                'costo_total' => round($costoTotal),
                'proveedor' => self::buscarNombreProveedor($producto, $colorId),
            ];
        };

        // Cálculo perfiles
        $addLinea(46, 2 * $cantidad, $alto + 5);
        $addLinea(46, 2 * $cantidad, $ancho + 5);
        $addLinea(47, 2 * $cantidad, $alto - 80);
        $addLinea(47, 2 * $cantidad, $ancho - 80);
        $addLinea(48, $hojasTotales * 2 * $cantidad, $alto - 54 - 54 + 16 + 5);
        $addLinea(48, $hojasTotales * 2 * $cantidad, ((($ancho - 92) / 2) + 45));
        $addLinea(49, $hojasTotales * 2 * $cantidad, $alto - 54 - 54 + 16 + 5 - 62 - 20);
        $addLinea(49, $hojasTotales * 2 * $cantidad, ((((($ancho - 92) / 2) + 45) - 62 - 25)));
        $addLinea(50, $hojasTotales * $cantidad, $alto - 54 - 54 + 16 + 5 - 7);
        $addLinea(51, 2 * $cantidad, $ancho - 54 - 54 - 1);
        $addLinea(52, $hojasTotales * 2 * $cantidad, ((($ancho - 92) / 2) + 45 - 62 - 62 - 5));
        $addLinea(52, $hojasTotales * 2 * $cantidad, $alto - 54 - 54 + 16 + 5 - 62 - 62 - 5);

        // Calcular vidrio por hoja (aplica a todas las hojas)
        $anchoHoja = ((($ancho - 92) / 2) + 45 - 62 - 62 - 5 - 8);
        $altoHoja = ($alto - 54 - 54 + 16 + 5 - 62 - 62 - 5);
        $areaHoja = ($anchoHoja / 1000) * ($altoHoja / 1000);

        $productoVidrio = $productos[$productoVidrioId];
        $costoVidrio = self::buscarCostoPorColor($productoVidrio, $colorId, $proveedorVidrioId);
        
        $materiales[] = [
            'producto_id' => $productoVidrio->id,
            'nombre' => $productoVidrio->nombre,
            'unidad' => 'm2',
            'cantidad' => round($areaHoja * $hojasTotales, 3) * $cantidad,
            'costo_unitario' => round($costoVidrio),
            'costo_total' => round($costoVidrio * $areaHoja * $hojasTotales)* $cantidad,
            'proveedor' => self::buscarNombreProveedor($productoVidrio, null, $proveedorVidrioId),
        ];

        // Carros por hoja móvil
        $pesoVidrioM2 = $productoVidrio->peso_por_metro ?? 0.2;
        $pesoPerfil = $productos[48]->peso_por_metro * ($altoHoja / 1000);
        $pesoRefuerzo = $productos[49]->peso_por_metro * ($altoHoja / 1000);
        $pesoVidrio = $pesoVidrioM2 * $areaHoja;
        $pesoHoja = $pesoPerfil + $pesoRefuerzo + $pesoVidrio;

        $carroId = $pesoHoja <= 30 ? 65 : ($pesoHoja <= 60 ? 66 : 67);
        $carro = $productos[$carroId];
        $costoCarro = self::buscarCostoPorColor($carro, $colorId);
        $materiales[] = [
            'producto_id' => $carro->id,
            'nombre' => $carro->nombre,
            'unidad' => 'unidad',
            'cantidad' => $hojasMoviles * 2 * $cantidad,
            'costo_unitario' => round($costoCarro),
            'costo_total' => round($costoCarro * $hojasMoviles * 2 * $cantidad),
            'proveedor' => $carro->coloresPorProveedor->first()?->proveedor?->nombre ?? 'N/A',
        ];

        // Cremona por hoja móvil
        $cremonaId = 55;
        if ($altoHoja >= 540) $cremonaId = 56;
        if ($altoHoja >= 740) $cremonaId = 57;
        if ($altoHoja >= 1000) $cremonaId = 58;
        if ($altoHoja >= 1200) $cremonaId = 59;
        if ($altoHoja >= 1400) $cremonaId = 60;
        if ($altoHoja >= 1600) $cremonaId = 61;
        if ($altoHoja >= 1800) $cremonaId = 62;
        if ($altoHoja >= 2000) $cremonaId = 63;

        $cremona = $productos[$cremonaId];
        $costoCremona = self::buscarCostoPorColor($cremona, $colorId);
        $materiales[] = [
            'producto_id' => $cremona->id,
            'nombre' => $cremona->nombre,
            'unidad' => 'unidad',
            'cantidad' => $hojasMoviles * $cantidad,
            'costo_unitario' => round($costoCremona),
            'costo_total' => round($costoCremona * $hojasMoviles * $cantidad),
            'proveedor' => $cremona->coloresPorProveedor->first()?->proveedor?->nombre ?? 'N/A',
        ];

        // Manilla por hoja móvil
        $manilla = $productos[68];
        $costoManilla = self::buscarCostoPorColor($manilla, $colorId);
        $materiales[] = [
            'producto_id' => $manilla->id,
            'nombre' => $manilla->nombre,
            'unidad' => 'unidad',
            'cantidad' => $hojasMoviles * $cantidad,
            'costo_unitario' => round($costoManilla),
            'costo_total' => round($costoManilla * $hojasMoviles * $cantidad),
            'proveedor' => $manilla->coloresPorProveedor->first()?->proveedor?->nombre ?? 'N/A',
        ];

            $costoTotal = array_sum(array_column($materiales, 'costo_total'));

            return [
                'materiales' => $materiales,
                'costo_total' => round($costoTotal),
                'costo_unitario' => round($costoTotal / $cantidad),
            ];
    }

    protected static function calcularProyectanteS60(array $ventana): array
{
    $alto = $ventana['alto'];
    $ancho = $ventana['ancho'];
    $colorId = $ventana['color'];
    $productoVidrioId = $ventana['productoVidrio'];
    $proveedorVidrioId = $ventana['proveedorVidrio'];
    $tipoVidrioId = $ventana['tipoVidrio'] ?? null;
    $cantidad = $ventana['cantidad'] ?? 1;

    

    // IDs de productos
    $perfilIds = [
        32, 72, 34, 49, 35, // perfiles y junquillos
        $productoVidrioId, // vidrio
        40, 41, 42, 43, 44, // tornillos, silicona, tapas
        77,78,79,80,81,82,83,84,85,86, // cremona
        87,88,89,90,91,92, // brazos
    ];

            $idPuente = 36;
        $idCalzoAmarillo = 37;
        $idCalzoCeleste = 38;
        $idCalzoRojo = 39;
        $idTornilloAuto = 40;
        $idTornilloAmo = 41;
        $idTapaDesague = 43;
        $idTapaTornillo = 42;
        $idSilicona = 44;

    $productos = Producto::with(['coloresPorProveedor.proveedor'])
        ->whereIn('id', $perfilIds)
        ->get()
        ->keyBy('id');

    $materiales = [];

    // Cálculo perfiles y junquillos (según fórmula de imagen)
    $materiales[] = self::crearLinea($productos[32], 2 * $cantidad, $ancho + 5, $colorId);
    $materiales[] = self::crearLinea($productos[32], 2 * $cantidad, $alto + 5, $colorId);
    $materiales[] = self::crearLinea($productos[72], 2 * $cantidad, $ancho - 48 - 48 + 21, $colorId);
    $materiales[] = self::crearLinea($productos[72], 2 * $cantidad, $alto - 48 - 48 + 16 + 5, $colorId);
    $materiales[] = self::crearLinea($productos[34], 2 * $cantidad, $ancho - 48 - 48 - 20, $colorId);
    $materiales[] = self::crearLinea($productos[34], 2 * $cantidad, $alto - 48 - 48 - 20, $colorId);
    $materiales[] = self::crearLinea($productos[49], 2 * $cantidad, $ancho -48-48+16-57.25-57.25-20, $colorId);
    $materiales[] = self::crearLinea($productos[49], 2 * $cantidad, $alto -48-48+16-57.25-57.25-20, $colorId);
    $materiales[] = self::crearLinea($productos[35], 2 * $cantidad, $ancho -48-48+16-57.25-57.25, $colorId);
    $materiales[] = self::crearLinea($productos[35], 2 * $cantidad, $alto -48-48+16-57.25-57.25, $colorId);

    // Cálculo vidrio
    $anchoHoja = $ancho - 48 - 48 + 21;
    $altoHoja = $alto - 48 - 48 + 16 + 5;
    $areaHoja = ($anchoHoja / 1000) * ($altoHoja / 1000);
    $productoVidrio = $productos[$productoVidrioId];
    $costoVidrio = self::buscarCostoPorColor($productoVidrio, $colorId, $proveedorVidrioId);
    $materiales[] = [
        'producto_id' => $productoVidrio->id,
        'nombre' => $productoVidrio->nombre,
        'unidad' => 'm2',
        'cantidad' => round($areaHoja * $cantidad, 3),
        'costo_unitario' => round($costoVidrio),
        'costo_total' => round($costoVidrio * $areaHoja * $cantidad),
        'proveedor' => self::buscarNombreProveedor($productoVidrio, $colorId, $proveedorVidrioId),
    ];

    // Cálculo cremona
    $cremonaId = match (true) {
        $anchoHoja >= 2120 => 82,
        $anchoHoja >= 1920 => 81,
        $anchoHoja >= 1720 => 80,
        $anchoHoja >= 1520 => 79,
        $anchoHoja >= 1320 => 78,
        $anchoHoja >= 1120 => 77,
        $anchoHoja >= 920  => 86,
        $anchoHoja >= 720  => 85,
        $anchoHoja >= 520  => 84,
        $anchoHoja >= 210  => 83,
        default => null,
    };

    if ($cremonaId) {
        $cremona = $productos[$cremonaId];
        $costoCremona = self::buscarCostoPorColor($cremona, $colorId);
        $materiales[] = [
            'producto_id' => $cremona->id,
            'nombre' => $cremona->nombre,
            'unidad' => 'unidad',
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoCremona),
            'costo_total' => round($costoCremona * $cantidad),
            'proveedor' => self::buscarNombreProveedor($cremona, $colorId),
        ];
    }

    // Cálculo peso hoja para brazo
    $pesoPerfil = $productos[72]->peso_por_metro * ($altoHoja / 1000);
    $pesoRefuerzo = $productos[49]->peso_por_metro * ($altoHoja / 1000);
    $pesoVidrio = $productoVidrio->peso_por_metro * $areaHoja;
    $pesoHoja = $pesoPerfil + $pesoRefuerzo + $pesoVidrio;

    // Buscar brazo según alto y peso
    $brazoId = null;
    $brazoReglas = [
        ['id' => 92, 'a_min' => 296, 'a_max' => 400, 'p_min' => 0, 'p_max' => 10],
        ['id' => 87, 'a_min' => 296, 'a_max' => 400, 'p_min' => 10.1, 'p_max' => 16],
        ['id' => 88, 'a_min' => 396, 'a_max' => 400, 'p_min' => 16.1, 'p_max' => 22.2],
        ['id' => 87, 'a_min' => 401, 'a_max' => 500, 'p_min' => 0, 'p_max' => 16],
        ['id' => 88, 'a_min' => 401, 'a_max' => 500, 'p_min' => 16.1, 'p_max' => 21],
        ['id' => 89, 'a_min' => 496, 'a_max' => 500, 'p_min' => 16.1, 'p_max' => 22],
        ['id' => 88, 'a_min' => 501, 'a_max' => 600, 'p_min' => 0, 'p_max' => 21.1],
        ['id' => 89, 'a_min' => 501, 'a_max' => 600, 'p_min' => 21.1, 'p_max' => 22],
        ['id' => 90, 'a_min' => 596, 'a_max' => 600, 'p_min' => 22.1, 'p_max' => 24],
        ['id' => 89, 'a_min' => 601, 'a_max' => 700, 'p_min' => 0, 'p_max' => 22],
        ['id' => 90, 'a_min' => 601, 'a_max' => 700, 'p_min' => 22.1, 'p_max' => 24],
        ['id' => 91, 'a_min' => 696, 'a_max' => 700, 'p_min' => 24.1, 'p_max' => 32],
        ['id' => 90, 'a_min' => 701, 'a_max' => 800, 'p_min' => 22.1, 'p_max' => 24],
        ['id' => 89, 'a_min' => 701, 'a_max' => 800, 'p_min' => 0, 'p_max' => 22],
        ['id' => 91, 'a_min' => 701, 'a_max' => 800, 'p_min' => 24.1, 'p_max' => 32],
        ['id' => 91, 'a_min' => 901, 'a_max' => 1400, 'p_min' => 0, 'p_max' => 32],
    ];

    foreach ($brazoReglas as $regla) {
        if ($altoHoja >= $regla['a_min'] && $altoHoja <= $regla['a_max'] &&
            $pesoHoja >= $regla['p_min'] && $pesoHoja <= $regla['p_max']) {
            $brazoId = $regla['id'];
            break;
        }
    }

    if ($brazoId) {
        $brazo = $productos[$brazoId];
        $costoBrazo = self::buscarCostoPorColor($brazo, $colorId);
        $materiales[] = [
            'producto_id' => $brazo->id,
            'nombre' => $brazo->nombre,
            'unidad' => 'unidad',
            'cantidad' => 2 * $cantidad,
            'costo_unitario' => round($costoBrazo),
            'costo_total' => round($costoBrazo * 2 * $cantidad),
            'proveedor' => self::buscarNombreProveedor($brazo, $colorId),
        ];
    }

       $manillaId = self::obtenerManillaEstrechaIdPorColor($colorId);

        if ($manillaId) {
            $manilla = ProductoColorProveedor::with('producto')->find($manillaId);

            $materiales[] = [
                'producto_id' => $manilla->producto_id,
                'nombre' => $manilla->producto->nombre,
                'unidad' => 'unidad',
                'cantidad' => $cantidad, // ✅ cambiar aquí
                'costo_unitario' => round($manilla->costo),
                'costo_total' => round($manilla->costo * $cantidad), // ✅ y aquí
                'proveedor' => self::buscarNombreProveedor($manilla->producto, null, $manilla->proveedor_id),
            ];
        }

    

    // === Herrajes universales ===

        // Silicona (ID 44)
        $materiales[] = self::crearHerraje($productos[44], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1) * $cantidad, $colorId);

        // Tornillos autoperforantes (ID 40)
        $materiales[] = self::crearHerraje($productos[40], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2) * $cantidad), $colorId);

        // Tornillos amortiguados (ID 41)
        $materiales[] = self::crearHerraje($productos[41], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);

        // Tapa tornillo (ID 42)
        $materiales[] = self::crearHerraje($productos[42], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);

        // Tapa desagüe (ID 43)
        $materiales[] = self::crearHerraje($productos[43], $alto, $ancho, self::calcularCantidadTapaDesague($ancho) * $cantidad, $colorId);


    $costoTotal = array_sum(array_column($materiales, 'costo_total'));
    $costoUnitario = $cantidad > 0 ? $costoTotal / $cantidad : 0;

    return [
    'materiales' => $materiales,
    'costo_total' => round($costoTotal),
    'costo_unitario' => round($costoUnitario),
];
}
        protected static function calcularCorrederaAndes(array $ventana): array
    {
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $tipoVidrioId = $ventana['tipoVidrio'] ?? 2;
        $hojasTotales = $ventana['hojas_totales'] ?? 2;
        $hojasMoviles = $ventana['hojas_moviles'] ?? 2;
        $cantidad = $ventana['cantidad'] ?? 1;

        $idJunquillo = match ($tipoVidrioId) {
            1 => 95, // Monolítico
            2 => 96, // Termopanel
            default => 96,
        };

        $ids = [
            98, 94, 101, 103, 104, 51,
            105,106,107,108,109,110,111,112,113,
            114,115, $idJunquillo, $productoVidrioId
        ];

        $productos = Producto::with('coloresPorProveedor.proveedor')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $materiales = [];

        $addLinea = function ($id, $cant, $largo) use (&$materiales, $productos, $colorId) {
            $producto = $productos[$id];
            $largoMt = $largo / 1000;
            $costoBarra = self::buscarCostoPorColor($producto, $colorId);
            $costoPorMetro = $producto->largo_total > 0 ? $costoBarra / $producto->largo_total : 0;
            $materiales[] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'unidad' => 'm',
                'cantidad' => round($cant * $largoMt, 3),
                'costo_unitario' => round($costoPorMetro),
                'costo_total' => round($cant * $largoMt * $costoPorMetro),
                'proveedor' => self::buscarNombreProveedor($producto, $colorId),
            ];
        };

        $addLinea(98, 2 * $cantidad, $ancho + 5);
        $addLinea(98, 2 * $cantidad, $alto + 5);
        $addLinea(94, 4 * $cantidad, (($ancho - 88 + 16) / 2 + 33 + 5));
        $addLinea(94, 4 * $cantidad, $alto - 44 - 44 + 20 + 5);
        $addLinea($idJunquillo, 4 * $cantidad, (($ancho - 88 + 16) / 2 + 33 + 5) - 109);
        $addLinea($idJunquillo, 4 * $cantidad, ($alto - 44 - 44 + 20 + 5) - 109);
        $addLinea(101, 2 * $cantidad, ($alto - 44 - 44 + 20 + 5 - 6));
        $addLinea(103, 4 * $cantidad, (($ancho - 88 + 16) / 2 + 33 + 5) - 117);
        $addLinea(103, 4 * $cantidad, ($alto - 44 - 44 + 20 + 5) - 117);
        $addLinea(104, 2 * $cantidad, $ancho + 5 - 84);
        $addLinea(104, 2 * $cantidad, $alto + 5 - 84);
        $addLinea(51, 2 * $cantidad, $ancho - 44 - 44 - 1);

        $anchoVidrio = (($ancho - 88 + 16) / 2 + 33 + 5) - 109 - 6;
        $altoVidrio = ($alto - 44 - 44 + 20 + 5) - 109 - 6;
        $areaVidrio = ($anchoVidrio / 1000) * ($altoVidrio / 1000);

        $productoVidrio = $productos[$productoVidrioId];
        $costoVidrio = self::buscarCostoPorColor($productoVidrio, $colorId, $proveedorVidrioId);

        $materiales[] = [
            'producto_id' => $productoVidrio->id,
            'nombre' => $productoVidrio->nombre,
            'unidad' => 'm2',
            'cantidad' => round($areaVidrio * $cantidad * 2, 3),
            'costo_unitario' => round($costoVidrio),
            'costo_total' => round($costoVidrio * $areaVidrio * 2 * $cantidad),
            'proveedor' => self::buscarNombreProveedor($productoVidrio, $colorId, $proveedorVidrioId),
        ];

        $pesoHoja = $productos[94]->peso_por_metro * ($altoVidrio / 1000)
                    + $productos[$idJunquillo]->peso_por_metro * ($altoVidrio / 1000)
                    + $productoVidrio->peso_por_metro * $areaVidrio
                    + $productos[103]->peso_por_metro * ($altoVidrio / 1000);

        $carroId = $pesoHoja <= 45 ? 114 : 115;
        $carro = $productos[$carroId];
        $costoCarro = self::buscarCostoPorColor($carro, $colorId);
        $materiales[] = [
            'producto_id' => $carro->id,
            'nombre' => $carro->nombre,
            'unidad' => 'unidad',
            'cantidad' => $hojasMoviles * 2 * $cantidad,
            'costo_unitario' => round($costoCarro),
            'costo_total' => round($costoCarro * ($hojasMoviles == 1 ? 2 : 4) * $cantidad),
            'proveedor' => self::buscarNombreProveedor($carro, $colorId),
        ];

        $cremonaId = null;
        $ranges = [
            [400, 716, 111], [717, 916, 112], [917, 1116, 113],
            [1117, 1316, 105], [1317, 1516, 106], [1517, 1716, 107],
            [1717, 1916, 108], [1917, 2116, 109], [2117, 2316, 110]
        ];
        foreach ($ranges as [$min, $max, $id]) {
            if ($alto >= $min && $alto <= $max) {
                $cremonaId = $id;
                break;
            }
        }

        if ($cremonaId) {
            $cremona = $productos[$cremonaId];
            $costoCremona = self::buscarCostoPorColor($cremona, $colorId);
            $materiales[] = [
                'producto_id' => $cremona->id,
                'nombre' => $cremona->nombre,
                'unidad' => 'unidad',
                'cantidad' => $hojasMoviles * $cantidad,
                'costo_unitario' => round($costoCremona),
                'costo_total' => round($costoCremona * ($hojasMoviles == 1 ? 1 : 2) * $cantidad),
                'proveedor' => self::buscarNombreProveedor($cremona, $colorId),
            ];
        }

        $costoTotal = array_sum(array_column($materiales, 'costo_total'));
        return [
            'materiales' => $materiales,
            'costo_total' => round($costoTotal),
            'costo_unitario' => round($costoTotal / max($cantidad, 1)),
        ];
    }


  
    public static function obtenerManillaEstrechaIdPorColor($colorId)
    {
        return match ($colorId) {
            1 => 122, // Blanco
            4 => 123, // Roble o Nogal
            2 => 124, // Negro o Grafito
            default => null,
        };
    }







    protected static function crearLinea($producto, $cantidad, $largoMm, $colorId)
    {
        $largoMt = $largoMm / 1000;
        $costoBarra = self::buscarCostoPorColor($producto, $colorId);
        $costoPorMetro = $producto->largo_total > 0 ? $costoBarra / $producto->largo_total : 0;
        $costoTotal = $cantidad * $largoMt * $costoPorMetro;

        return [
            'producto_id' => $producto->id,
            'nombre' => $producto->nombre,
            'unidad' => 'm',
            'cantidad' => round($cantidad * $largoMt, 3),
            'costo_unitario' => round($costoPorMetro),
            'costo_total' => round($costoTotal),
            'proveedor' => self::buscarNombreProveedor($producto, $colorId)
        ];
    }

    protected static function crearHerraje($producto, $alto, $ancho, $cantidad = null, $colorId = 3)
    {
        $cantidad = $cantidad ?? 6;
        $costoUnitario = self::buscarCostoPorColor($producto, $colorId);
        return [
            'producto_id' => $producto->id,
            'nombre' => $producto->nombre,
            'unidad' => 'unidad',
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoUnitario),
            'costo_total' => round($costoUnitario * $cantidad),
            'proveedor' => self::buscarNombreProveedor($producto, $colorId),
        ];
    }

    
    protected static function buscarCostoPorColor($producto, $colorId = null, $proveedorId = null)
    {
        $colorData = self::encontrarColorProveedor($producto, $colorId, $proveedorId);
        return $colorData?->costo ?? 0;
    }

    protected static function buscarNombreProveedor($producto, $colorId = null, $proveedorId = null)
    {
        $colorData = self::encontrarColorProveedor($producto, $colorId, $proveedorId);
        return $colorData?->proveedor?->nombre ?? 'N/A';
    }

    protected static function encontrarColorProveedor($producto, $colorId = null, $proveedorId = null)
{
    // 1. Buscar por color y proveedor exacto
    if ($colorId && $proveedorId) {
        $match = $producto->coloresPorProveedor->first(fn($item) =>
            $item->color_id == $colorId && $item->proveedor_id == $proveedorId
        );
        if ($match) return $match;
    }

    // 2. Buscar por proveedor (si es vidrio o específico)
    if ($proveedorId && in_array($producto->tipo_producto_id, [1, 2])) {
        $match = $producto->coloresPorProveedor->first(fn($item) => $item->proveedor_id == $proveedorId);
        if ($match) return $match;
    }

    // 3. Buscar por color
    if ($colorId) {
        $match = $producto->coloresPorProveedor->first(fn($item) => $item->color_id == $colorId);
        if ($match) return $match;
    }

    // 4. Último recurso: primer proveedor disponible
    return $producto->coloresPorProveedor->first();
}

    protected static function calcularCantidadTapaDesague($ancho)
    {
        return $ancho <= 800 ? 2 : ($ancho <= 1500 ? 3 : 4);
    }

    protected static function calcularCantidadTapaTornillo($alto, $ancho)
    {
        return (($alto * 2) / 500 + ($ancho * 2) / 500);
    }
}
