<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\Log;

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

        $materiales[] = self::crearLinea($productos[$idMarco], 2, $alto, $colorId);
        $materiales[] = self::crearLinea($productos[$idMarco], 2, $ancho, $colorId);
        $materiales[] = self::crearLinea($productos[$idRefuerzo], 2, $alto, $colorId);
        $materiales[] = self::crearLinea($productos[$idRefuerzo], 2, $ancho, $colorId);
        $materiales[] = self::crearLinea($productos[$idJunquillo], 2, $alto, $colorId);
        $materiales[] = self::crearLinea($productos[$idJunquillo], 2, $ancho, $colorId);

        $materiales[] = self::crearHerraje($productos[$idPuente], $alto, $ancho, null, $colorId);
        $materiales[] = self::crearHerraje($productos[$idCalzoAmarillo], $alto, $ancho, null, $colorId);
        $materiales[] = self::crearHerraje($productos[$idCalzoCeleste], $alto, $ancho, null, $colorId);
        $materiales[] = self::crearHerraje($productos[$idCalzoRojo], $alto, $ancho, null, $colorId);

        $materiales[] = self::crearHerraje($productos[$idTornilloAuto], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2)), $colorId);
        $materiales[] = self::crearHerraje($productos[$idTornilloAmo], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500)), $colorId);
        $materiales[] = self::crearHerraje($productos[$idTapaDesague], $alto, $ancho, self::calcularCantidadTapaDesague($ancho), $colorId);
        $materiales[] = self::crearHerraje($productos[$idTapaTornillo], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500)), $colorId);
        $materiales[] = self::crearHerraje($productos[$idSilicona], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1), $colorId);

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
        'cantidad' => round($areaM2, 3),
        'costo_unitario' => round($costoVidrio),
        'costo_total' => round($costoVidrio * $areaM2),
        'proveedor' => $matchVidrio?->proveedor?->nombre ?? 'N/A',
    ];

        $costoTotal = array_sum(array_column($materiales, 'costo_total'));

        return [
            'materiales' => $materiales,
            'costo_total' => $costoTotal,
        ];
    }

    protected static function calcularCorrederaSliding(array $ventana): array
    {
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $hojasTotales = $ventana['hojas_totales'] ?? 2;
        $hojasMoviles = $ventana['hojas_moviles'] ?? 2;

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
        $addLinea(46, 2, $alto + 5);
        $addLinea(46, 2, $ancho + 5);
        $addLinea(47, 2, $alto - 80);
        $addLinea(47, 2, $ancho - 80);
        $addLinea(48, $hojasTotales * 2, $alto - 54 - 54 + 16 + 5);
        $addLinea(48, $hojasTotales * 2, ((($ancho - 92) / 2) + 45));
        $addLinea(49, $hojasTotales * 2, $alto - 54 - 54 + 16 + 5 - 62 - 20);
        $addLinea(49, $hojasTotales * 2, ((((($ancho - 92) / 2) + 45) - 62 - 25)));
        $addLinea(50, $hojasTotales, $alto - 54 - 54 + 16 + 5 - 7);
        $addLinea(51, 2, $ancho - 54 - 54 - 1);
        $addLinea(52, $hojasTotales * 2, ((($ancho - 92) / 2) + 45 - 62 - 62 - 5));
        $addLinea(52, $hojasTotales * 2, $alto - 54 - 54 + 16 + 5 - 62 - 62 - 5);

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
            'cantidad' => round($areaHoja * $hojasTotales, 3),
            'costo_unitario' => round($costoVidrio),
            'costo_total' => round($costoVidrio * $areaHoja * $hojasTotales),
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
            'cantidad' => $hojasMoviles * 2,
            'costo_unitario' => round($costoCarro),
            'costo_total' => round($costoCarro * $hojasMoviles * 2),
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
            'cantidad' => $hojasMoviles,
            'costo_unitario' => round($costoCremona),
            'costo_total' => round($costoCremona * $hojasMoviles),
            'proveedor' => $cremona->coloresPorProveedor->first()?->proveedor?->nombre ?? 'N/A',
        ];

        // Manilla por hoja móvil
        $manilla = $productos[68];
        $costoManilla = self::buscarCostoPorColor($manilla, $colorId);
        $materiales[] = [
            'producto_id' => $manilla->id,
            'nombre' => $manilla->nombre,
            'unidad' => 'unidad',
            'cantidad' => $hojasMoviles,
            'costo_unitario' => round($costoManilla),
            'costo_total' => round($costoManilla * $hojasMoviles),
            'proveedor' => $manilla->coloresPorProveedor->first()?->proveedor?->nombre ?? 'N/A',
        ];

        $costoTotal = array_sum(array_column($materiales, 'costo_total'));

        return [
            'materiales' => $materiales,
            'costo_total' => $costoTotal,
        ];
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
