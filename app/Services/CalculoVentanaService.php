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

        // ✅ Ventana fija AL42
        if ($tipoVentanaId == 1) {
            return self::calcularFijaAL42($ventana);
        }

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

        if ($tipoVentanaId == 53) {
            return self::calcularCorrederaMonorriel($ventana);
        }

        if ($tipoVentanaId == 49) {
            return self::calcularAbatirS60($ventana);
        }
        // ✅ NUEVO: Puerta S60
        if ($tipoVentanaId == 50) {
            return self::calcularPuertaS60($ventana);
        }

        if ($tipoVentanaId == 51) {
            return self::calcularPuerta2HojasS60($ventana);
        }

        if ($tipoVentanaId == 52) {
            return self::calcularCorrederaSliding98($ventana);
        }

        if ($tipoVentanaId == 47) {
        return self::calcularBayWindow($ventana);
        }           

        return [
            'materiales' => [],
            'costo_total' => 0,
        ];
    }

    // ✅ VENTANA FIJA AL42 - ALUMINIO
    protected static function calcularFijaAL42(array $ventana): array
    {
        Log::info("🪟 Calculando Fija AL42 - Parámetros recibidos:", $ventana);

        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $productoVidrioId = $ventana['productoVidrio'];
        $colorId = $ventana['color'];
        $proveedorVidrio = $ventana['proveedorVidrio'];
        $tipoVidrioId = $ventana['tipoVidrio'] ?? null;
        $cantidad = $ventana['cantidad'] ?? 1;

        Log::info("🪟 Configuración Fija AL42", [
            'alto' => $alto,
            'ancho' => $ancho,
            'tipo_vidrio_raw' => $tipoVidrioId,
            'tipo_vidrio_type' => gettype($tipoVidrioId),
            'cantidad' => $cantidad,
            'color' => $colorId
        ]);

        // ✅ Determinar qué junquillo usar según tipo de vidrio
        $idJunquillo = match ($tipoVidrioId) {
            1 => 151, // Monolítico (código 4229)
            2 => 153, // Termopanel (código 4206)
            default => 151, // Default monolítico
        };

        Log::info("🔍 Junquillo seleccionado", [
            'tipo_vidrio_id' => $tipoVidrioId,
            'junquillo_id' => $idJunquillo,
            'nombre' => $tipoVidrioId == 1 ? 'Monolítico (151/4229)' : 'Termopanel (153/4206)'
        ]);

        // ✅ Tabla de descuentos según imagen
        // id_producto | DESC. | CANT. | Fórmula
        $perfilesConfig = [
            ['id' => 148, 'desc' => 'X', 'cant' => 1, 'formula' => fn($x, $y) => $x], // Marco superior
            ['id' => 152, 'desc' => 'X+40', 'cant' => 1, 'formula' => fn($x, $y) => $x + 40], // Marco inferior
            ['id' => 148, 'desc' => 'Y-20', 'cant' => 2, 'formula' => fn($x, $y) => $y - 20], // Marco lateral (2x)
            ['id' => $idJunquillo, 'desc' => 'X-25.6', 'cant' => 2, 'formula' => fn($x, $y) => $x - 25.6], // Junquillo horizontal (2x)
            ['id' => $idJunquillo, 'desc' => 'Y-45.61', 'cant' => 2, 'formula' => fn($x, $y) => $y - 45.61], // Junquillo vertical (2x)
        ];

        // ✅ IDs de herrajes universales (iguales a S60)
        $idPuente = 36;
        $idCalzoAmarillo = 37;
        $idCalzoCeleste = 38;
        $idCalzoRojo = 39;
        $idTornilloAuto = 40;
        $idTornilloAmo = 41;
        $idTapaDesague = 43;
        $idTapaTornillo = 42;
        $idSilicona = 44;

        // ✅ IDs únicos de perfiles AL42 + herrajes + vidrio
        $perfilIds = array_merge(
            array_unique(array_column($perfilesConfig, 'id')),
            [$productoVidrioId, $idPuente, $idCalzoAmarillo, $idCalzoCeleste, $idCalzoRojo, 
             $idTornilloAuto, $idTornilloAmo, $idTapaDesague, $idTapaTornillo, $idSilicona]
        );

        $productos = Producto::with('coloresPorProveedor.proveedor')
            ->whereIn('id', $perfilIds)
            ->get()
            ->keyBy('id');

        Log::info("📦 Productos cargados", [
            'solicitados' => count($perfilIds),
            'encontrados' => count($productos),
            'faltantes' => array_diff($perfilIds, $productos->keys()->toArray())
        ]);

        $materiales = [];

        // ✅ Calcular perfiles según tabla (incluye junquillo condicional)
        foreach ($perfilesConfig as $config) {
            $largoMm = $config['formula']($ancho, $alto);
            $cantidadTotal = $config['cant'] * $cantidad;
            
            if (isset($productos[$config['id']])) {
                $materiales[] = self::crearLinea($productos[$config['id']], $cantidadTotal, $largoMm, $colorId);
                
                Log::info("✅ Perfil agregado", [
                    'id' => $config['id'],
                    'desc' => $config['desc'],
                    'cantidad' => $cantidadTotal,
                    'largo_mm' => $largoMm
                ]);
            } else {
                Log::warning("⚠️ Producto no encontrado", ['id' => $config['id']]);
            }
        }

        // ✅ Herrajes universales (iguales a S60)
        $materiales[] = self::crearHerraje($productos[$idPuente], $alto, $ancho, null, $colorId);
        $materiales[] = self::crearHerraje($productos[$idCalzoAmarillo], $alto, $ancho, null, $colorId);
        $materiales[] = self::crearHerraje($productos[$idCalzoCeleste], $alto, $ancho, null, $colorId);
        $materiales[] = self::crearHerraje($productos[$idCalzoRojo], $alto, $ancho, null, $colorId);
        $materiales[] = self::crearHerraje($productos[$idTornilloAuto], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[$idTornilloAmo], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[$idTapaDesague], $alto, $ancho, self::calcularCantidadTapaDesague($ancho) * $cantidad, $colorId);
        $materiales[] = self::crearHerraje($productos[$idTapaTornillo], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[$idSilicona], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1) * $cantidad, $colorId);

        // ✅ Cálculo vidrio AL42 - Dimensiones específicas
        // X = ancho - 45.6
        // Y = alto - 65.6
        $anchoVidrio = $ancho - 45.6;
        $altoVidrio = $alto - 65.6;
        $areaM2 = ($anchoVidrio / 1000) * ($altoVidrio / 1000);
        
        $productoVidrio = $productos[$productoVidrioId];
        
        // Verifica combinación exacta color + proveedor
        $vidrioMatch = $productoVidrio->coloresPorProveedor->first(function ($cpp) use ($colorId, $proveedorVidrio) {
            return (int)$cpp->color_id === (int)$colorId && (int)$cpp->proveedor_id === (int)$proveedorVidrio;
        });

        // Si no existe, intenta con color_id 3
        $colorIdVidrio = $vidrioMatch ? $colorId : 3;
        $costoVidrio = self::buscarCostoPorColor($productoVidrio, $colorIdVidrio, $proveedorVidrio);
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

        Log::info("💰 Fija AL42 calculada", [
            'costo_total' => $costoTotal,
            'costo_unitario' => round($costoTotal / $cantidad),
            'materiales_count' => count($materiales),
            'ancho_vidrio_mm' => $anchoVidrio,
            'alto_vidrio_mm' => $altoVidrio,
            'area_vidrio_m2' => $areaM2
        ]);

        return [
            'materiales' => $materiales,
            'costo_total' => round($costoTotal),
            'costo_unitario' => round($costoTotal / $cantidad),
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
            'costo_total' => round($costoVidrio * $areaHoja * $hojasTotales) * $cantidad,
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

    protected static function calcularCorrederaSliding98(array $ventana): array
    {
        Log::info("🪟 Calculando Corredera Sliding 98 - Parámetros recibidos:", $ventana);
        
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $tipoVidrioId = $ventana['tipoVidrio'] ?? 2; // Default termopanel
        $hojasTotales = $ventana['hojas_totales'] ?? 2;
        $hojasMoviles = $ventana['hojas_moviles'] ?? 2;
        $cantidad = $ventana['cantidad'] ?? 1;

        Log::info("🪟 Configuración Corredera Sliding 98", [
            'alto' => $alto,
            'ancho' => $ancho,
            'hojas_totales' => $hojasTotales,
            'hojas_moviles' => $hojasMoviles,
            'tipo_vidrio' => $tipoVidrioId,
            'cantidad' => $cantidad
        ]);

        // ✅ IDs específicos para Corredera Sliding 98 (según tabla)
        $junquilloId = $tipoVidrioId == 1 ? 45 : 35; // Monolítico (45) o Termopanel (35)
        
        $productoIds = [
            46,  // Marco doble riel corredera (igual que original)
            47,  // Refuerzo marco corredera 1,2 MM (igual que original)
            146, // ✅ NUEVO: Hoja corredera 98 (en lugar de ID 48)
            145, // ✅ NUEVO: Refuerzo hoja corredera 98 2 MM (en lugar de ID 49)
            50,  // Hoja corredera 98 (igual que original) 
            51,  // Riel de aluminio (igual que original)
            147, // ✅ NUEVO: Traslapo corredera 98 (en lugar de ID 52)
            $junquilloId, // Junquillo según tipo vidrio
            $productoVidrioId, // Vidrio
            // Hardware (igual que original)
            55, 56, 57, 58, 59, 60, 61, 62, 63, // Cremonas
            65, 66, 67, // Carros
            68, // Manilla
            // Herrajes universales (agregar si faltan)
            40, 41, 42, 43, 44, // tornillos, silicona, tapas
        ];

        $productos = Producto::with(['coloresPorProveedor.proveedor'])
            ->whereIn('id', $productoIds)
            ->get()
            ->keyBy('id');

        $materiales = [];

        // Helper function para agregar líneas
        $addLinea = function ($id, $cantidad, $largoMm) use (&$materiales, $productos, $colorId) {
            $producto = $productos[$id];
            $largoMt = $largoMm / 1000;
            $costoBarra = self::buscarCostoPorColor($producto, $colorId);
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

        // ✅ Cálculo perfiles según tabla - MISMAS FÓRMULAS pero con IDs nuevos
        
        // Marco doble riel corredera (ID 46) - igual que original
        $addLinea(46, 2 * $cantidad, $ancho + 5);
        $addLinea(46, 2 * $cantidad, $alto + 5);
        
        // Refuerzo marco corredera (ID 47) - igual que original  
        $addLinea(47, 2 * $cantidad, $ancho + 5 - 85);
        $addLinea(47, 2 * $cantidad, $alto + 5 - 85);
        
        // ✅ NUEVO: Hoja corredera 98 (ID 146) - fórmulas según tabla
        $addLinea(146, 4 * $cantidad, (($ancho - 108 + 16) / 2) + 54);
        $addLinea(146, 4 * $cantidad, $alto - 54 - 54 + 16 + 5);
        
        // ✅ NUEVO: Refuerzo hoja corredera 98 (ID 145) - fórmulas según tabla
        $addLinea(145, 4 * $cantidad, (($ancho - 108 + 16) / 2) + 54 - 80 - 80 - 25);
        $addLinea(145, 4 * $cantidad, $alto - 54 - 54 + 16 + 5 - 80 - 80 - 25);
        
        // Hoja corredera 98 (ID 50) - igual que original
        $addLinea(50, $hojasTotales * $cantidad, $alto - 54 - 54 + 16 + 5 - 7);
        
        // Riel de aluminio (ID 51) - igual que original
        $addLinea(51, 2 * $cantidad, $ancho - 54 - 54 - 1);
        
        // ✅ NUEVO: Traslapo corredera 98 (ID 147) - fórmulas según tabla
        $addLinea(147, 2 * $cantidad, $ancho - 54 - 54 + 16 + 5);
        
        // Junquillo según tipo vidrio - fórmulas según tabla
        $addLinea($junquilloId, 4 * $cantidad, (($ancho - 108 + 16) / 2) + 54 - 80 - 80 - 5);
        $addLinea($junquilloId, 4 * $cantidad, $alto - 54 - 54 + 16 + 5 - 80 - 80 - 5);

        // ✅ Calcular vidrio por hoja - FÓRMULA ESPECÍFICA según tabla
        $anchoHoja = (($ancho - 108 + 16) / 2) + 54 - 80 - 80 - 5 - 8; // ✅ Fórmula tabla
        $altoHoja = ($alto - 54 - 54 + 16 + 5 - 80 - 80 - 5) - 8; // ✅ Fórmula tabla  
        $areaHoja = ($anchoHoja / 1000) * ($altoHoja / 1000);

        Log::info("🔍 Cálculo vidrio Corredera 98", [
            'ancho_hoja' => $anchoHoja,
            'alto_hoja' => $altoHoja,
            'area_hoja' => $areaHoja,
            'hojas_totales' => $hojasTotales
        ]);

        $productoVidrio = $productos[$productoVidrioId];
        $costoVidrio = self::buscarCostoPorColor($productoVidrio, $colorId, $proveedorVidrioId);

        $materiales[] = [
            'producto_id' => $productoVidrio->id,
            'nombre' => $productoVidrio->nombre,
            'unidad' => 'm2',
            'cantidad' => round($areaHoja * $hojasTotales, 3) * $cantidad, // ✅ 2 cristales por ventana
            'costo_unitario' => round($costoVidrio),
            'costo_total' => round($costoVidrio * $areaHoja * $hojasTotales) * $cantidad,
            'proveedor' => self::buscarNombreProveedor($productoVidrio, null, $proveedorVidrioId),
        ];

        // ✅ HARDWARE - Igual que Corredera Sliding original

        // Carros por hoja móvil (según peso)
        $pesoVidrioM2 = $productoVidrio->peso_por_metro ?? 0.2;
        $pesoPerfil = $productos[146]->peso_por_metro * ($altoHoja / 1000); // ✅ Usar ID 146
        $pesoRefuerzo = $productos[145]->peso_por_metro * ($altoHoja / 1000); // ✅ Usar ID 145
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
            'proveedor' => self::buscarNombreProveedor($carro, $colorId),
        ];

        // Cremona por hoja móvil (según alto)
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
            'proveedor' => self::buscarNombreProveedor($cremona, $colorId),
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
            'proveedor' => self::buscarNombreProveedor($manilla, $colorId),
        ];

        // ✅ Herrajes universales (agregar si faltan en la original)
        $materiales[] = self::crearHerraje($productos[44], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1) * $cantidad, $colorId);
        $materiales[] = self::crearHerraje($productos[40], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[41], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[42], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[43], $alto, $ancho, self::calcularCantidadTapaDesague($ancho) * $cantidad, $colorId);

        $costoTotal = array_sum(array_column($materiales, 'costo_total'));
        $costoUnitario = $cantidad > 0 ? $costoTotal / $cantidad : 0;

        Log::info("💰 Corredera Sliding 98 calculada", [
            'costo_total' => $costoTotal,
            'costo_unitario' => $costoUnitario,
            'hojas_totales' => $hojasTotales,
            'hojas_moviles' => $hojasMoviles,
            'area_vidrio_total' => $areaHoja * $hojasTotales,
            'peso_hoja' => $pesoHoja,
            'carro_usado' => $carroId,
            'cremona_usada' => $cremonaId,
            'diferencias' => 'IDs 146, 145, 147 vs originales 48, 49, 52'
        ]);

        return [
            'materiales' => $materiales,
            'costo_total' => round($costoTotal),
            'costo_unitario' => round($costoUnitario),
        ];
    }


    protected static function calcularPuertaS60(array $ventana): array
    {
        Log::info("🚪 Calculando Puerta S60 - Parámetros recibidos:", $ventana);

        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $tipoVidrioId = $ventana['tipoVidrio'] ?? 2; // Default termopanel
        $cantidad = $ventana['cantidad'] ?? 1;

        // ✅ Parámetros específicos de puerta
        $direccionApertura = $ventana['direccionApertura'] ?? 'exterior';
        $pasoLibre = $ventana['pasoLibre'] ?? false; // true = sin perfil inferior

        Log::info("🚪 Configuración Puerta S60", [
            'direccion' => $direccionApertura,
            'pasoLibre' => $pasoLibre,
            'tipoVidrio' => $tipoVidrioId,
            'alto' => $alto,
            'ancho' => $ancho,
            'cantidad' => $cantidad
        ]);

        if ($direccionApertura === 'exterior') {
            return self::calcularPuertaExterior($ventana, $pasoLibre, $tipoVidrioId);
        } else {
            return self::calcularPuertaInterior($ventana, $pasoLibre, $tipoVidrioId);
        }
    }

    // ✅ APERTURA EXTERIOR - Agregar lógica paso libre
    protected static function calcularPuertaExterior(array $ventana, bool $pasoLibre, int $tipoVidrioId): array
    {
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $cantidad = $ventana['cantidad'] ?? 1;

        Log::info("🔧 Puerta EXTERIOR", [
            'paso_libre' => $pasoLibre,
            'tipo_vidrio' => $tipoVidrioId
        ]);

        // ✅ IDs según la imagen para APERTURA EXTERIOR
        $perfilIds = [
            32,  // Marco FIJO S60
            135, // Hoja puerta exterior
            34,  // Ref. box marco fijo
            137, // Ref. puerta 1,5
            // Junquillo según tipo vidrio
            $tipoVidrioId == 1 ? 45 : 35, // Monolítico (45) o Termopanel (35)
            $productoVidrioId, // vidrio
            // Hardware específico puerta
            141, // Manilla
            140, // Bisagras
            138,
            139, // Cremonas
            144,// cilindro
            40,
            41,
            42,
            43,
            44, // tornillos, silicona, tapas
        ];

        $productos = Producto::with(['coloresPorProveedor.proveedor'])
            ->whereIn('id', $perfilIds)
            ->get()
            ->keyBy('id');

        $materiales = [];

        // ✅ DIFERENCIA CLAVE: Cantidad de marcos según paso libre
        $cantidadMarcoAncho = $pasoLibre ? 1 : 2; // ✅ Paso libre = 1, Paso cerrado = 2

        // Marco FIJO S60 (ID 32)
        $materiales[] = self::crearLinea($productos[32], $cantidadMarcoAncho * $cantidad, $ancho + 5, $colorId);
        $materiales[] = self::crearLinea($productos[32], 2 * $cantidad, $alto + 5, $colorId); // Alto siempre 2

        // Hoja puerta exterior (ID 135)
        $materiales[] = self::crearLinea($productos[135], 2 * $cantidad, $ancho - 48 - 48 + 16 + 5, $colorId);
        $materiales[] = self::crearLinea($productos[135], 2 * $cantidad, $alto - 48 - 48 + 16 + 5, $colorId); // Alto siempre 2

        // Ref. box marco fijo (ID 34)
        $materiales[] = self::crearLinea($productos[34], $cantidadMarcoAncho * $cantidad, $ancho - 48 - 48 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[34], 2 * $cantidad, $alto - 48 - 48 - 20, $colorId); // Alto siempre 2

        // Ref. puerta 1,5 (ID 137)
        $materiales[] = self::crearLinea($productos[137], 2 * $cantidad, $ancho - 48 - 48 + 16 - 93.75 - 93.75 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[137], 2 * $cantidad, $alto - 48 - 48 + 16 - 93.75 - 93.75 - 20, $colorId); // Alto siempre 2

        // ✅ Junquillo según tipo vidrio
        $junquilloId = $tipoVidrioId == 1 ? 45 : 35; // Monolítico o Termopanel
        $materiales[] = self::crearLinea($productos[$junquilloId], 2 * $cantidad, $ancho - 48 - 48 + 16 - 93.75 - 93.75, $colorId);
        $materiales[] = self::crearLinea($productos[$junquilloId], 2 * $cantidad, $alto - 48 - 48 + 16 - 93.75 - 93.75, $colorId); // Alto siempre 2

        // ✅ Cálculo vidrio (igual)
        $anchoVidrio = $ancho - 48 - 48 + 16 - 93.75 - 93.75 - 8;
        $altoVidrio = $alto - 48 - 48 + 16 - 93.75 - 93.75 - 8;
        $areaVidrio = ($anchoVidrio / 1000) * ($altoVidrio / 1000);

        $productoVidrio = $productos[$productoVidrioId];
        $costoVidrio = self::buscarCostoPorColor($productoVidrio, $colorId, $proveedorVidrioId);
        $materiales[] = [
            'producto_id' => $productoVidrio->id,
            'nombre' => $productoVidrio->nombre,
            'unidad' => 'm2',
            'cantidad' => round($areaVidrio * $cantidad, 3),
            'costo_unitario' => round($costoVidrio),
            'costo_total' => round($costoVidrio * $areaVidrio * $cantidad),
            'proveedor' => self::buscarNombreProveedor($productoVidrio, $colorId, $proveedorVidrioId),
        ];

        // ✅ HARDWARE ESPECÍFICO PUERTA (igual)

        // Manilla (ID 141)
        $manilla = $productos[141];
        $costoManilla = self::buscarCostoPorColor($manilla, $colorId);
        $materiales[] = [
            'producto_id' => $manilla->id,
            'nombre' => $manilla->nombre,
            'unidad' => 'unidad',
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoManilla),
            'costo_total' => round($costoManilla * $cantidad),
            'proveedor' => self::buscarNombreProveedor($manilla, $colorId),
        ];

        // Bisagras (ID 140) - cantidad según alto
        $cantidadBisagras = $alto <= 2250 ? 4 : 5;
        $bisagras = $productos[140];
        $costoBisagras = self::buscarCostoPorColor($bisagras, $colorId);
        $materiales[] = [
            'producto_id' => $bisagras->id,
            'nombre' => $bisagras->nombre,
            'unidad' => 'unidad',
            'cantidad' => $cantidadBisagras * $cantidad,
            'costo_unitario' => round($costoBisagras),
            'costo_total' => round($costoBisagras * $cantidadBisagras * $cantidad),
            'proveedor' => self::buscarNombreProveedor($bisagras, $colorId),
        ];

        // Cremona según alto
        $cremonaId = $alto < 2120 ? 138 : 139; // 138 = cremona 1800, 139 = cremona 2000
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

         // ✅ AGREGAR: Cilindro (ID 144) - Faltaba en puerta 1 hoja
        $cilindro = $productos[144];
        $costoCilindro = self::buscarCostoPorColor($cilindro, $colorId);
        $materiales[] = [
            'producto_id' => $cilindro->id,
            'nombre' => $cilindro->nombre,
            'unidad' => 'unidad',
            'cantidad' => $cantidad, // ✅ 1 cilindro por puerta
            'costo_unitario' => round($costoCilindro),
            'costo_total' => round($costoCilindro * $cantidad),
            'proveedor' => self::buscarNombreProveedor($cilindro, $colorId),
        ];

        // ✅ Herrajes universales (iguales)
        $materiales[] = self::crearHerraje($productos[44], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1) * $cantidad, $colorId);
        $materiales[] = self::crearHerraje($productos[40], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[41], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[42], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[43], $alto, $ancho, self::calcularCantidadTapaDesague($ancho) * $cantidad, $colorId);

        $costoTotal = array_sum(array_column($materiales, 'costo_total'));
        $costoUnitario = $cantidad > 0 ? $costoTotal / $cantidad : 0;

        Log::info("💰 Puerta EXTERIOR calculada", [
            'costo_total' => $costoTotal,
            'paso_libre' => $pasoLibre,
            'cantidad_marcos_ancho' => $cantidadMarcoAncho,
            'cantidad_bisagras' => $cantidadBisagras,
            'cremona_usada' => $cremonaId,
            'junquillo_usado' => $junquilloId
        ]);

        return [
            'materiales' => $materiales,
            'costo_total' => round($costoTotal),
            'costo_unitario' => round($costoUnitario),
        ];
    }

    // ✅ APERTURA INTERIOR - Agregar lógica paso libre
    protected static function calcularPuertaInterior(array $ventana, bool $pasoLibre, int $tipoVidrioId): array
    {
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $cantidad = $ventana['cantidad'] ?? 1;

        Log::info("🔧 Puerta INTERIOR", [
            'paso_libre' => $pasoLibre,
            'tipo_vidrio' => $tipoVidrioId
        ]);

        // ✅ IDs según la tabla para APERTURA INTERIOR
        $perfilIds = [
            32,  // Marco FIJO S60 (igual que exterior)
            136, // ✅ Hoja puerta interior (diferente del exterior que usa 135)
            34,  // Ref. box marco fijo (igual que exterior)
            137, // Ref. puerta 1,5 (igual que exterior)
            // Junquillo según tipo vidrio (igual que exterior)
            $tipoVidrioId == 1 ? 45 : 35, // Monolítico (45) o Termopanel (35)
            $productoVidrioId, // vidrio
            // Hardware específico puerta (igual que exterior)
            141, // Manilla (igual que exterior)
            140, // Bisagras (igual que exterior)
            138,
            139, // Cremonas (igual que exterior)
            144, // Cilindro (igual que exterior)
            40,
            41,
            42,
            43,
            44, // tornillos, silicona, tapas
        ];

        $productos = Producto::with(['coloresPorProveedor.proveedor'])
            ->whereIn('id', $perfilIds)
            ->get()
            ->keyBy('id');

        $materiales = [];

        // ✅ DIFERENCIA CLAVE: Cantidad de marcos según paso libre
        $cantidadMarcoAncho = $pasoLibre ? 1 : 2; // ✅ Paso libre = 1, Paso cerrado = 2

        // Marco FIJO S60 (ID 32) - igual que exterior
        $materiales[] = self::crearLinea($productos[32], $cantidadMarcoAncho * $cantidad, $ancho + 5, $colorId);
        $materiales[] = self::crearLinea($productos[32], 2 * $cantidad, $alto + 5, $colorId); // Alto siempre 2

        // ✅ DIFERENCIA: Hoja puerta interior (ID 136) en lugar de 135
        $materiales[] = self::crearLinea($productos[136], 2 * $cantidad, $ancho - 48 - 48 + 21, $colorId);
        $materiales[] = self::crearLinea($productos[136], 2 * $cantidad, $alto - 48 - 48 + 16 + 5, $colorId); // Alto siempre 2

        // Ref. box marco fijo (ID 34) - igual que exterior
        $materiales[] = self::crearLinea($productos[34], $cantidadMarcoAncho * $cantidad, $ancho - 48 - 48 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[34], 2 * $cantidad, $alto - 48 - 48 - 20, $colorId); // Alto siempre 2

        // Ref. puerta 1,5 (ID 137) - igual que exterior
        $materiales[] = self::crearLinea($productos[137], 2 * $cantidad, $ancho - 48 - 48 + 16 - 93.75 - 93.75 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[137], 2 * $cantidad, $alto - 48 - 48 + 16 - 93.75 - 93.75 - 20, $colorId); // Alto siempre 2

        // ✅ Junquillo según tipo vidrio (igual que exterior)
        $junquilloId = $tipoVidrioId == 1 ? 45 : 35; // Monolítico o Termopanel
        $materiales[] = self::crearLinea($productos[$junquilloId], 2 * $cantidad, $ancho - 48 - 48 + 16 - 93.75 - 93.75, $colorId);
        $materiales[] = self::crearLinea($productos[$junquilloId], 2 * $cantidad, $alto - 48 - 48 + 16 - 93.75 - 93.75, $colorId); // Alto siempre 2

        // ✅ Cálculo vidrio (igual que exterior)
        $anchoVidrio = $ancho - 48 - 48 + 16 - 93.75 - 93.75 - 8;
        $altoVidrio = $alto - 48 - 48 + 16 - 93.75 - 93.75 - 8;
        $areaVidrio = ($anchoVidrio / 1000) * ($altoVidrio / 1000);

        $productoVidrio = $productos[$productoVidrioId];
        $costoVidrio = self::buscarCostoPorColor($productoVidrio, $colorId, $proveedorVidrioId);
        $materiales[] = [
            'producto_id' => $productoVidrio->id,
            'nombre' => $productoVidrio->nombre,
            'unidad' => 'm2',
            'cantidad' => round($areaVidrio * $cantidad, 3),
            'costo_unitario' => round($costoVidrio),
            'costo_total' => round($costoVidrio * $areaVidrio * $cantidad),
            'proveedor' => self::buscarNombreProveedor($productoVidrio, $colorId, $proveedorVidrioId),
        ];

        // ✅ HARDWARE ESPECÍFICO PUERTA (igual que exterior)

        // Manilla (ID 141)
        $manilla = $productos[141];
        $costoManilla = self::buscarCostoPorColor($manilla, $colorId);
        $materiales[] = [
            'producto_id' => $manilla->id,
            'nombre' => $manilla->nombre,
            'unidad' => 'unidad',
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoManilla),
            'costo_total' => round($costoManilla * $cantidad),
            'proveedor' => self::buscarNombreProveedor($manilla, $colorId),
        ];

        // Bisagras (ID 140) - cantidad según alto
        $cantidadBisagras = $alto <= 2250 ? 4 : 5;
        $bisagras = $productos[140];
        $costoBisagras = self::buscarCostoPorColor($bisagras, $colorId);
        $materiales[] = [
            'producto_id' => $bisagras->id,
            'nombre' => $bisagras->nombre,
            'unidad' => 'unidad',
            'cantidad' => $cantidadBisagras * $cantidad,
            'costo_unitario' => round($costoBisagras),
            'costo_total' => round($costoBisagras * $cantidadBisagras * $cantidad),
            'proveedor' => self::buscarNombreProveedor($bisagras, $colorId),
        ];

        // Cremona según alto (igual que exterior)
        $cremonaId = $alto < 2120 ? 138 : 139; // 138 = cremona 1800, 139 = cremona 2000
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

        
        // ✅ AGREGAR: Cilindro (ID 144) - Faltaba en puerta 1 hoja
        $cilindro = $productos[144];
        $costoCilindro = self::buscarCostoPorColor($cilindro, $colorId);
        $materiales[] = [
            'producto_id' => $cilindro->id,
            'nombre' => $cilindro->nombre,
            'unidad' => 'unidad',
            'cantidad' => $cantidad, // ✅ 1 cilindro por puerta
            'costo_unitario' => round($costoCilindro),
            'costo_total' => round($costoCilindro * $cantidad),
            'proveedor' => self::buscarNombreProveedor($cilindro, $colorId),
        ];

        // ✅ Herrajes universales (iguales que exterior)
        $materiales[] = self::crearHerraje($productos[44], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1) * $cantidad, $colorId);
        $materiales[] = self::crearHerraje($productos[40], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[41], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[42], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[43], $alto, $ancho, self::calcularCantidadTapaDesague($ancho) * $cantidad, $colorId);

        $costoTotal = array_sum(array_column($materiales, 'costo_total'));
        $costoUnitario = $cantidad > 0 ? $costoTotal / $cantidad : 0;

        Log::info("💰 Puerta INTERIOR calculada", [
            'costo_total' => $costoTotal,
            'paso_libre' => $pasoLibre,
            'cantidad_marcos_ancho' => $cantidadMarcoAncho,
            'cantidad_bisagras' => $cantidadBisagras,
            'cremona_usada' => $cremonaId,
            'junquillo_usado' => $junquilloId,
            'diferencia_con_exterior' => 'Perfil hoja ID 136 vs 135'
        ]);

        return [
            'materiales' => $materiales,
            'costo_total' => round($costoTotal),
            'costo_unitario' => round($costoUnitario),
        ];
    }


    protected static function calcularPuerta2HojasS60(array $ventana): array
    {
        Log::info("🚪🚪 Calculando Puerta 2 Hojas S60 - Parámetros recibidos:", $ventana);

        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $tipoVidrioId = $ventana['tipoVidrio'] ?? 2; // Default termopanel
        $cantidad = $ventana['cantidad'] ?? 1;

        // ✅ Parámetros específicos de puerta 2 hojas
        $direccionApertura = $ventana['direccionApertura'] ?? 'exterior';
        $pasoLibre = $ventana['pasoLibre'] ?? false; // true = sin perfil inferior
        $hojaActiva = $ventana['hojaActiva'] ?? 'izquierda'; // ¿cuál hoja tiene manilla?

        Log::info("🚪🚪 Configuración Puerta 2 Hojas S60", [
            'direccion' => $direccionApertura,
            'pasoLibre' => $pasoLibre,
            'hojaActiva' => $hojaActiva,
            'tipoVidrio' => $tipoVidrioId,
            'alto' => $alto,
            'ancho' => $ancho,
            'cantidad' => $cantidad
        ]);

        if ($direccionApertura === 'exterior') {
            return self::calcularPuerta2HojasExterior($ventana, $pasoLibre, $tipoVidrioId, $hojaActiva);
        } else {
            return self::calcularPuerta2HojasInterior($ventana, $pasoLibre, $tipoVidrioId, $hojaActiva);
        }
    }


    // ✅ APERTURA EXTERIOR 2 HOJAS - Completar con nuevos IDs
    protected static function calcularPuerta2HojasExterior(array $ventana, bool $pasoLibre, int $tipoVidrioId, string $hojaActiva): array
    {
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $cantidad = $ventana['cantidad'] ?? 1;

        Log::info("🔧 Puerta 2 Hojas EXTERIOR", [
            'paso_libre' => $pasoLibre,
            'tipo_vidrio' => $tipoVidrioId,
            'hoja_activa' => $hojaActiva
        ]);

        // ✅ IDs actualizados para APERTURA EXTERIOR 2 HOJAS
        $perfilIds = [
            32,  // Marco FIJO S60
            135, // Hoja puerta exterior
            34,  // Ref. box marco fijo
            137, // Ref. puerta 1,5
            142, // ✅ NUEVO: Perfil inversor/poste (entre las 2 hojas)
            // Junquillo según tipo vidrio
            $tipoVidrioId == 1 ? 45 : 35, // Monolítico (45) o Termopanel (35)
            $productoVidrioId, // vidrio
            // Hardware específico puerta 2 hojas
            141, // Manilla (1 sola para hoja activa)
            140, // Bisagras (8 total = 4 por hoja)
            138,
            139, // Cremonas
            143, // ✅ NUEVO: Picaporte (2 unidades)
            144, // ✅ NUEVO: Cilindro (1 unidad)
            // Herrajes universales
            40,
            41,
            42,
            43,
            44, // tornillos, silicona, tapas
        ];

        $productos = Producto::with(['coloresPorProveedor.proveedor'])
            ->whereIn('id', $perfilIds)
            ->get()
            ->keyBy('id');

        $materiales = [];

        // ✅ Cantidad según paso libre
        $cantidadMarcoAncho = $pasoLibre ? 1 : 2;

        // Marco FIJO S60 (ID 32)
        $materiales[] = self::crearLinea($productos[32], $cantidadMarcoAncho * $cantidad, $ancho + 5, $colorId);
        $materiales[] = self::crearLinea($productos[32], 2 * $cantidad, $alto + 5, $colorId);

        // Hoja puerta exterior (ID 135) - DOBLE cantidad por 2 hojas
        $materiales[] = self::crearLinea($productos[135], 4 * $cantidad, ($ancho / 2) - 48 + 16 + 5, $colorId);
        $materiales[] = self::crearLinea($productos[135], 4 * $cantidad, $alto - 48 - 48 + 16 + 5, $colorId);

        // Ref. box marco fijo (ID 34)
        $materiales[] = self::crearLinea($productos[34], $cantidadMarcoAncho * $cantidad, $ancho - 48 - 48 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[34], 2 * $cantidad, $alto - 48 - 48 - 20, $colorId);

        // Ref. puerta 1,5 (ID 137) - DOBLE cantidad por 2 hojas
        $materiales[] = self::crearLinea($productos[137], 4 * $cantidad, ($ancho / 2) - 48 + 16 - 93.75 - 93.75 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[137], 4 * $cantidad, $alto - 48 - 48 + 16 - 93.75 - 93.75 - 20, $colorId);

        // ✅ NUEVO: Perfil inversor/poste (ID 142) - Entre las 2 hojas
        $materiales[] = self::crearLinea($productos[142], 1 * $cantidad, $alto - 48 - 48, $colorId); // ✅ Solo vertical

        // Junquillo según tipo vidrio - DOBLE cantidad por 2 hojas
        $junquilloId = $tipoVidrioId == 1 ? 45 : 35;
        $materiales[] = self::crearLinea($productos[$junquilloId], 4 * $cantidad, ($ancho / 2) - 48 + 16 - 93.75 - 93.75, $colorId);
        $materiales[] = self::crearLinea($productos[$junquilloId], 4 * $cantidad, $alto - 48 - 48 + 16 - 93.75 - 93.75, $colorId);

        // Cálculo vidrio - DOBLE área por 2 hojas
        $anchoVidrioPorHoja = ($ancho / 2) - 48 + 16 - 93.75 - 93.75 - 8;
        $altoVidrio = $alto - 48 - 48 + 16 - 93.75 - 93.75 - 8;
        $areaVidrioPorHoja = ($anchoVidrioPorHoja / 1000) * ($altoVidrio / 1000);
        $areaVidrioTotal = $areaVidrioPorHoja * 2;

        $productoVidrio = $productos[$productoVidrioId];
        $costoVidrio = self::buscarCostoPorColor($productoVidrio, $colorId, $proveedorVidrioId);
        $materiales[] = [
            'producto_id' => $productoVidrio->id,
            'nombre' => $productoVidrio->nombre,
            'unidad' => 'm2',
            'cantidad' => round($areaVidrioTotal * $cantidad, 3),
            'costo_unitario' => round($costoVidrio),
            'costo_total' => round($costoVidrio * $areaVidrioTotal * $cantidad),
            'proveedor' => self::buscarNombreProveedor($productoVidrio, $colorId, $proveedorVidrioId),
        ];

        // ✅ HARDWARE ESPECÍFICO PUERTA 2 HOJAS

        // Manilla (ID 141) - SOLO 1 para hoja activa
        $manilla = $productos[141];
        $costoManilla = self::buscarCostoPorColor($manilla, $colorId);
        $materiales[] = [
            'producto_id' => $manilla->id,
            'nombre' => $manilla->nombre . " (hoja {$hojaActiva})",
            'unidad' => 'unidad',
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoManilla),
            'costo_total' => round($costoManilla * $cantidad),
            'proveedor' => self::buscarNombreProveedor($manilla, $colorId),
        ];

        // Bisagras (ID 140) - 8 total = 4 por hoja
        $cantidadBisagrasPorHoja = $alto <= 2250 ? 4 : 5;
        $cantidadBisagrasTotal = $cantidadBisagrasPorHoja * 2;
        $bisagras = $productos[140];
        $costoBisagras = self::buscarCostoPorColor($bisagras, $colorId);
        $materiales[] = [
            'producto_id' => $bisagras->id,
            'nombre' => $bisagras->nombre . " (2 hojas)",
            'unidad' => 'unidad',
            'cantidad' => $cantidadBisagrasTotal * $cantidad,
            'costo_unitario' => round($costoBisagras),
            'costo_total' => round($costoBisagras * $cantidadBisagrasTotal * $cantidad),
            'proveedor' => self::buscarNombreProveedor($bisagras, $colorId),
        ];

        // Cremona según alto (igual que puerta 1 hoja)
        $cremonaId = $alto < 2120 ? 138 : 139;
        $cremona = $productos[$cremonaId];
        $costoCremona = self::buscarCostoPorColor($cremona, $colorId);
        $materiales[] = [
            'producto_id' => $cremona->id,
            'nombre' => $cremona->nombre . " (hoja activa)",
            'unidad' => 'unidad',
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoCremona),
            'costo_total' => round($costoCremona * $cantidad),
            'proveedor' => self::buscarNombreProveedor($cremona, $colorId),
        ];

        // ✅ NUEVO: Picaporte (ID 143) - Siempre 2 unidades
        $picaporte = $productos[143];
        $costoPicaporte = self::buscarCostoPorColor($picaporte, $colorId);
        $materiales[] = [
            'producto_id' => $picaporte->id,
            'nombre' => $picaporte->nombre . " (2 hojas)",
            'unidad' => 'unidad',
            'cantidad' => 2 * $cantidad, // ✅ Siempre 2
            'costo_unitario' => round($costoPicaporte),
            'costo_total' => round($costoPicaporte * 2 * $cantidad),
            'proveedor' => self::buscarNombreProveedor($picaporte, $colorId),
        ];

        // ✅ NUEVO: Cilindro (ID 144) - 1 unidad
        $cilindro = $productos[144];
        $costoCilindro = self::buscarCostoPorColor($cilindro, $colorId);
        $materiales[] = [
            'producto_id' => $cilindro->id,
            'nombre' => $cilindro->nombre,
            'unidad' => 'unidad',
            'cantidad' => $cantidad, // ✅ Solo 1 cilindro por puerta
            'costo_unitario' => round($costoCilindro),
            'costo_total' => round($costoCilindro * $cantidad),
            'proveedor' => self::buscarNombreProveedor($cilindro, $colorId),
        ];

        // Herrajes universales (proporcionales al área total)
        $materiales[] = self::crearHerraje($productos[44], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1) * $cantidad, $colorId);
        $materiales[] = self::crearHerraje($productos[40], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[41], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[42], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[43], $alto, $ancho, self::calcularCantidadTapaDesague($ancho) * $cantidad, $colorId);

        $costoTotal = array_sum(array_column($materiales, 'costo_total'));
        $costoUnitario = $cantidad > 0 ? $costoTotal / $cantidad : 0;

        Log::info("💰 Puerta 2 Hojas EXTERIOR calculada", [
            'costo_total' => $costoTotal,
            'paso_libre' => $pasoLibre,
            'cantidad_marcos_ancho' => $cantidadMarcoAncho,
            'cantidad_bisagras_total' => $cantidadBisagrasTotal,
            'cremona_usada' => $cremonaId,
            'junquillo_usado' => $junquilloId,
            'hoja_activa' => $hojaActiva,
            'area_vidrio_total' => $areaVidrioTotal,
            'perfil_inversor' => 'ID 142',
            'picaportes' => '2 unidades (ID 143)',
            'cilindro' => '1 unidad (ID 144)'
        ]);

        return [
            'materiales' => $materiales,
            'costo_total' => round($costoTotal),
            'costo_unitario' => round($costoUnitario),
        ];
    }

    // ✅ APERTURA INTERIOR 2 HOJAS - Implementar completa
    protected static function calcularPuerta2HojasInterior(array $ventana, bool $pasoLibre, int $tipoVidrioId, string $hojaActiva): array
    {
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $cantidad = $ventana['cantidad'] ?? 1;

        Log::info("🔧 Puerta 2 Hojas INTERIOR", [
            'paso_libre' => $pasoLibre,
            'tipo_vidrio' => $tipoVidrioId,
            'hoja_activa' => $hojaActiva
        ]);

        // ✅ IDs para APERTURA INTERIOR 2 HOJAS
        $perfilIds = [
            32,  // Marco FIJO S60 (igual que exterior)
            136, // ✅ Hoja puerta interior (diferente del exterior que usa 135)
            34,  // Ref. box marco fijo (igual que exterior)
            137, // Ref. puerta 1,5 (igual que exterior)
            142, // ✅ Perfil inversor/poste (igual que exterior)
            // Junquillo según tipo vidrio (igual que exterior)
            $tipoVidrioId == 1 ? 45 : 35, // Monolítico (45) o Termopanel (35)
            $productoVidrioId, // vidrio
            // Hardware específico puerta 2 hojas (igual que exterior)
            141, // Manilla
            140, // Bisagras
            138,
            139, // Cremonas
            143, // Picaporte
            144, // Cilindro
            // Herrajes universales (igual que exterior)
            40,
            41,
            42,
            43,
            44,
        ];

        $productos = Producto::with(['coloresPorProveedor.proveedor'])
            ->whereIn('id', $perfilIds)
            ->get()
            ->keyBy('id');

        $materiales = [];

        // Cantidad según paso libre
        $cantidadMarcoAncho = $pasoLibre ? 1 : 2;

        // Marco FIJO S60 (ID 32) - igual que exterior
        $materiales[] = self::crearLinea($productos[32], $cantidadMarcoAncho * $cantidad, $ancho + 5, $colorId);
        $materiales[] = self::crearLinea($productos[32], 2 * $cantidad, $alto + 5, $colorId);

        // ✅ DIFERENCIA: Hoja puerta interior (ID 136) en lugar de 135
        $materiales[] = self::crearLinea($productos[136], 4 * $cantidad, ($ancho / 2) - 48 + 21, $colorId);
        $materiales[] = self::crearLinea($productos[136], 4 * $cantidad, $alto - 48 - 48 + 16 + 5, $colorId);

        // Ref. box marco fijo (ID 34) - igual que exterior
        $materiales[] = self::crearLinea($productos[34], $cantidadMarcoAncho * $cantidad, $ancho - 48 - 48 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[34], 2 * $cantidad, $alto - 48 - 48 - 20, $colorId);

        // Ref. puerta 1,5 (ID 137) - igual que exterior
        $materiales[] = self::crearLinea($productos[137], 4 * $cantidad, ($ancho / 2) - 48 + 16 - 93.75 - 93.75 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[137], 4 * $cantidad, $alto - 48 - 48 + 16 - 93.75 - 93.75 - 20, $colorId);

        // Perfil inversor/poste (ID 142) - igual que exterior
        $materiales[] = self::crearLinea($productos[142], 1 * $cantidad, $alto - 48 - 48, $colorId);

        // Junquillo según tipo vidrio - igual que exterior
        $junquilloId = $tipoVidrioId == 1 ? 45 : 35;
        $materiales[] = self::crearLinea($productos[$junquilloId], 4 * $cantidad, ($ancho / 2) - 48 + 16 - 93.75 - 93.75, $colorId);
        $materiales[] = self::crearLinea($productos[$junquilloId], 4 * $cantidad, $alto - 48 - 48 + 16 - 93.75 - 93.75, $colorId);

        // Cálculo vidrio - igual que exterior
        $anchoVidrioPorHoja = ($ancho / 2) - 48 + 16 - 93.75 - 93.75 - 8;
        $altoVidrio = $alto - 48 - 48 + 16 - 93.75 - 93.75 - 8;
        $areaVidrioPorHoja = ($anchoVidrioPorHoja / 1000) * ($altoVidrio / 1000);
        $areaVidrioTotal = $areaVidrioPorHoja * 2;

        $productoVidrio = $productos[$productoVidrioId];
        $costoVidrio = self::buscarCostoPorColor($productoVidrio, $colorId, $proveedorVidrioId);
        $materiales[] = [
            'producto_id' => $productoVidrio->id,
            'nombre' => $productoVidrio->nombre,
            'unidad' => 'm2',
            'cantidad' => round($areaVidrioTotal * $cantidad, 3),
            'costo_unitario' => round($costoVidrio),
            'costo_total' => round($costoVidrio * $areaVidrioTotal * $cantidad),
            'proveedor' => self::buscarNombreProveedor($productoVidrio, $colorId, $proveedorVidrioId),
        ];

        // ✅ HARDWARE (igual que exterior)

        // Manilla, Bisagras, Cremona (código igual que exterior)
        $manilla = $productos[141];
        $costoManilla = self::buscarCostoPorColor($manilla, $colorId);
        $materiales[] = [
            'producto_id' => $manilla->id,
            'nombre' => $manilla->nombre . " (hoja {$hojaActiva})",
            'unidad' => 'unidad',
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoManilla),
            'costo_total' => round($costoManilla * $cantidad),
            'proveedor' => self::buscarNombreProveedor($manilla, $colorId),
        ];

        $cantidadBisagrasPorHoja = $alto <= 2250 ? 4 : 5;
        $cantidadBisagrasTotal = $cantidadBisagrasPorHoja * 2;
        $bisagras = $productos[140];
        $costoBisagras = self::buscarCostoPorColor($bisagras, $colorId);
        $materiales[] = [
            'producto_id' => $bisagras->id,
            'nombre' => $bisagras->nombre . " (2 hojas)",
            'unidad' => 'unidad',
            'cantidad' => $cantidadBisagrasTotal * $cantidad,
            'costo_unitario' => round($costoBisagras),
            'costo_total' => round($costoBisagras * $cantidadBisagrasTotal * $cantidad),
            'proveedor' => self::buscarNombreProveedor($bisagras, $colorId),
        ];

        $cremonaId = $alto < 2120 ? 138 : 139;
        $cremona = $productos[$cremonaId];
        $costoCremona = self::buscarCostoPorColor($cremona, $colorId);
        $materiales[] = [
            'producto_id' => $cremona->id,
            'nombre' => $cremona->nombre . " (hoja activa)",
            'unidad' => 'unidad',
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoCremona),
            'costo_total' => round($costoCremona * $cantidad),
            'proveedor' => self::buscarNombreProveedor($cremona, $colorId),
        ];

        // Picaporte (ID 143) - 2 unidades
        $picaporte = $productos[143];
        $costoPicaporte = self::buscarCostoPorColor($picaporte, $colorId);
        $materiales[] = [
            'producto_id' => $picaporte->id,
            'nombre' => $picaporte->nombre . " (2 hojas)",
            'unidad' => 'unidad',
            'cantidad' => 2 * $cantidad,
            'costo_unitario' => round($costoPicaporte),
            'costo_total' => round($costoPicaporte * 2 * $cantidad),
            'proveedor' => self::buscarNombreProveedor($picaporte, $colorId),
        ];

        // Cilindro (ID 144) - 1 unidad
        $cilindro = $productos[144];
        $costoCilindro = self::buscarCostoPorColor($cilindro, $colorId);
        $materiales[] = [
            'producto_id' => $cilindro->id,
            'nombre' => $cilindro->nombre,
            'unidad' => 'unidad',
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoCilindro),
            'costo_total' => round($costoCilindro * $cantidad),
            'proveedor' => self::buscarNombreProveedor($cilindro, $colorId),
        ];

        // Herrajes universales (iguales que exterior)
        $materiales[] = self::crearHerraje($productos[44], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1) * $cantidad, $colorId);
        $materiales[] = self::crearHerraje($productos[40], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[41], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[42], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[43], $alto, $ancho, self::calcularCantidadTapaDesague($ancho) * $cantidad, $colorId);

        $costoTotal = array_sum(array_column($materiales, 'costo_total'));
        $costoUnitario = $cantidad > 0 ? $costoTotal / $cantidad : 0;

        Log::info("💰 Puerta 2 Hojas INTERIOR calculada", [
            'costo_total' => $costoTotal,
            'diferencia_con_exterior' => 'Perfil hoja ID 136 vs 135',
            'perfil_inversor' => 'ID 142',
            'picaportes' => '2 unidades (ID 143)',
            'cilindro' => '1 unidad (ID 144)'
        ]);

        return [
            'materiales' => $materiales,
            'costo_total' => round($costoTotal),
            'costo_unitario' => round($costoUnitario),
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
            32,
            72,
            34,
            49,
            35, // perfiles y junquillos
            $productoVidrioId, // vidrio
            40,
            41,
            42,
            43,
            44, // tornillos, silicona, tapas
            77,
            78,
            79,
            80,
            81,
            82,
            83,
            84,
            85,
            86, // cremona
            87,
            88,
            89,
            90,
            91,
            92, // brazos
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
        $materiales[] = self::crearLinea($productos[49], 2 * $cantidad, $ancho - 48 - 48 + 16 - 57.25 - 57.25 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[49], 2 * $cantidad, $alto - 48 - 48 + 16 - 57.25 - 57.25 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[35], 2 * $cantidad, $ancho - 48 - 48 + 16 - 57.25 - 57.25, $colorId);
        $materiales[] = self::crearLinea($productos[35], 2 * $cantidad, $alto - 48 - 48 + 16 - 57.25 - 57.25, $colorId);

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
            if (
                $altoHoja >= $regla['a_min'] && $altoHoja <= $regla['a_max'] &&
                $pesoHoja >= $regla['p_min'] && $pesoHoja <= $regla['p_max']
            ) {
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

    protected static function calcularAbatirS60(array $ventana): array
    {
        // ✅ DEBUG: Ver todos los parámetros que llegan
        Log::info("🔍 DEBUGGING Abatir S60 - Parámetros recibidos:", $ventana);
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $tipoVidrioId = $ventana['tipoVidrio'] ?? null;
        $cantidad = $ventana['cantidad'] ?? 1;

        // ✅ Determinar si es apertura interior o exterior
        $direccionApertura = $ventana['direccionApertura'] ?? 'exterior';

        Log::info("🚪 Calculando Abatir S60", [
            'direccion' => $direccionApertura,
            'alto' => $alto,
            'ancho' => $ancho,
            'cantidad' => $cantidad
        ]);

        if ($direccionApertura === 'exterior') {
            return self::calcularAbatirExterior($ventana);
        } else {
            return self::calcularAbatirInterior($ventana);
        }
    }

    // ✅ APERTURA EXTERIOR (igual al proyectante)
    protected static function calcularAbatirExterior(array $ventana): array
    {
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $tipoVidrioId = $ventana['tipoVidrio'] ?? null;
        $cantidad = $ventana['cantidad'] ?? 1;

        // ✅ MISMOS IDs que proyectante PERO brazos van en ANCHO
        $perfilIds = [
            32,
            72,
            34,
            49,
            35, // perfiles y junquillos (iguales)
            $productoVidrioId, // vidrio
            40,
            41,
            42,
            43,
            44, // tornillos, silicona, tapas
            77,
            78,
            79,
            80,
            81,
            82,
            83,
            84,
            85,
            86, // cremona
            87,
            88,
            89,
            90,
            91,
            92, // brazos
        ];

        $productos = Producto::with(['coloresPorProveedor.proveedor'])
            ->whereIn('id', $perfilIds)
            ->get()
            ->keyBy('id');

        $materiales = [];

        // ✅ Perfiles (iguales al proyectante)
        $materiales[] = self::crearLinea($productos[32], 2 * $cantidad, $ancho + 5, $colorId);
        $materiales[] = self::crearLinea($productos[32], 2 * $cantidad, $alto + 5, $colorId);
        $materiales[] = self::crearLinea($productos[72], 2 * $cantidad, $ancho - 48 - 48 + 21, $colorId);
        $materiales[] = self::crearLinea($productos[72], 2 * $cantidad, $alto - 48 - 48 + 16 + 5, $colorId);
        $materiales[] = self::crearLinea($productos[34], 2 * $cantidad, $ancho - 48 - 48 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[34], 2 * $cantidad, $alto - 48 - 48 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[49], 2 * $cantidad, $ancho - 48 - 48 + 16 - 57.25 - 57.25 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[49], 2 * $cantidad, $alto - 48 - 48 + 16 - 57.25 - 57.25 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[35], 2 * $cantidad, $ancho - 48 - 48 + 16 - 57.25 - 57.25, $colorId);
        $materiales[] = self::crearLinea($productos[35], 2 * $cantidad, $alto - 48 - 48 + 16 - 57.25 - 57.25, $colorId);

        // ✅ Vidrio (igual)
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

        // ✅ Cremona (igual al proyectante - por alto)
        $cremonaId = match (true) {
            $altoHoja >= 2120 => 82,
            $altoHoja >= 1920 => 81,
            $altoHoja >= 1720 => 80,
            $altoHoja >= 1520 => 79,
            $altoHoja >= 1320 => 78,
            $altoHoja >= 1120 => 77,
            $altoHoja >= 920  => 86,
            $altoHoja >= 720  => 85,
            $altoHoja >= 520  => 84,
            $altoHoja >= 210  => 83,
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

        // ✅ DIFERENCIA CLAVE: Brazos van en ANCHO (no alto como proyectante)
        $pesoPerfil = $productos[72]->peso_por_metro * ($anchoHoja / 1000); // ✅ ANCHO
        $pesoRefuerzo = $productos[49]->peso_por_metro * ($anchoHoja / 1000); // ✅ ANCHO
        $pesoVidrio = $productoVidrio->peso_por_metro * $areaHoja;
        $pesoHoja = $pesoPerfil + $pesoRefuerzo + $pesoVidrio;

        // ✅ Buscar brazo según ANCHO y peso (no alto)
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
            // ✅ USA ANCHO en lugar de alto
            if (
                $anchoHoja >= $regla['a_min'] && $anchoHoja <= $regla['a_max'] &&
                $pesoHoja >= $regla['p_min'] && $pesoHoja <= $regla['p_max']
            ) {
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

        // ✅ Manilla (igual)
        $manillaId = self::obtenerManillaEstrechaIdPorColor($colorId);
        if ($manillaId) {
            $manilla = ProductoColorProveedor::with('producto')->find($manillaId);
            $materiales[] = [
                'producto_id' => $manilla->producto_id,
                'nombre' => $manilla->producto->nombre,
                'unidad' => 'unidad',
                'cantidad' => $cantidad,
                'costo_unitario' => round($manilla->costo),
                'costo_total' => round($manilla->costo * $cantidad),
                'proveedor' => self::buscarNombreProveedor($manilla->producto, null, $manilla->proveedor_id),
            ];
        }

        // ✅ Herrajes universales (iguales)
        $materiales[] = self::crearHerraje($productos[44], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1) * $cantidad, $colorId);
        $materiales[] = self::crearHerraje($productos[40], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[41], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[42], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[43], $alto, $ancho, self::calcularCantidadTapaDesague($ancho) * $cantidad, $colorId);

        $costoTotal = array_sum(array_column($materiales, 'costo_total'));
        $costoUnitario = $cantidad > 0 ? $costoTotal / $cantidad : 0;

        return [
            'materiales' => $materiales,
            'costo_total' => round($costoTotal),
            'costo_unitario' => round($costoUnitario),
        ];
    }

    // ✅ APERTURA INTERIOR (IDs diferentes + brazos en ancho)
    protected static function calcularAbatirInterior(array $ventana): array
    {
        $alto = $ventana['alto'];
        $ancho = $ventana['ancho'];
        $colorId = $ventana['color'];
        $productoVidrioId = $ventana['productoVidrio'];
        $proveedorVidrioId = $ventana['proveedorVidrio'];
        $tipoVidrioId = $ventana['tipoVidrio'] ?? null;
        $cantidad = $ventana['cantidad'] ?? 1;

        // ✅ IDs ESPECÍFICOS PARA APERTURA INTERIOR
        $perfilIds = [
            32, // Marco (igual)
            134, // ✅ Perfil hoja abatir interior (en lugar de 72)
            34, // Refuerzo marco (igual)
            49, // Refuerzo hoja (igual) 
            35, // Junquillo termopanel (igual)
            $productoVidrioId, // vidrio
            40,
            41,
            42,
            43,
            44, // tornillos, silicona, tapas (iguales)
            77,
            78,
            79,
            80,
            81,
            82,
            83,
            84,
            85,
            86, // cremona (iguales)
            87,
            88,
            89,
            90,
            91,
            92, // brazos (iguales)
        ];

        $productos = Producto::with(['coloresPorProveedor.proveedor'])
            ->whereIn('id', $perfilIds)
            ->get()
            ->keyBy('id');

        $materiales = [];

        // ✅ Perfiles - MISMAS FÓRMULAS pero con ID 71
        $materiales[] = self::crearLinea($productos[32], 2 * $cantidad, $ancho + 5, $colorId);
        $materiales[] = self::crearLinea($productos[32], 2 * $cantidad, $alto + 5, $colorId);
        $materiales[] = self::crearLinea($productos[134], 2 * $cantidad, $ancho - 48 - 48 + 21, $colorId); // ✅ 71
        $materiales[] = self::crearLinea($productos[134], 2 * $cantidad, $alto - 48 - 48 + 16 + 5, $colorId); // ✅ 71
        $materiales[] = self::crearLinea($productos[34], 2 * $cantidad, $ancho - 48 - 48 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[34], 2 * $cantidad, $alto - 48 - 48 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[49], 2 * $cantidad, $ancho - 48 - 48 + 16 - 57.25 - 57.25 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[49], 2 * $cantidad, $alto - 48 - 48 + 16 - 57.25 - 57.25 - 20, $colorId);
        $materiales[] = self::crearLinea($productos[35], 2 * $cantidad, $ancho - 48 - 48 + 16 - 57.25 - 57.25, $colorId);
        $materiales[] = self::crearLinea($productos[35], 2 * $cantidad, $alto - 48 - 48 + 16 - 57.25 - 57.25, $colorId);

        // ✅ Vidrio (igual)
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

        // ✅ Cremona (igual)
        $cremonaId = match (true) {
            $altoHoja >= 2120 => 82,
            $altoHoja >= 1920 => 81,
            $altoHoja >= 1720 => 80,
            $altoHoja >= 1520 => 79,
            $altoHoja >= 1320 => 78,
            $altoHoja >= 1120 => 77,
            $altoHoja >= 920  => 86,
            $altoHoja >= 720  => 85,
            $altoHoja >= 520  => 84,
            $altoHoja >= 210  => 83,
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

        // ✅ DIFERENCIA: Peso usando ID 71 + brazos en ANCHO
        $pesoPerfil = $productos[134]->peso_por_metro * ($anchoHoja / 1000); // ✅ ID 71 + ANCHO
        $pesoRefuerzo = $productos[49]->peso_por_metro * ($anchoHoja / 1000); // ✅ ANCHO
        $pesoVidrio = $productoVidrio->peso_por_metro * $areaHoja;
        $pesoHoja = $pesoPerfil + $pesoRefuerzo + $pesoVidrio;

        // ✅ Brazos según ANCHO
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
            // ✅ USA ANCHO
            if (
                $anchoHoja >= $regla['a_min'] && $anchoHoja <= $regla['a_max'] &&
                $pesoHoja >= $regla['p_min'] && $pesoHoja <= $regla['p_max']
            ) {
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

        // ✅ Manilla y herrajes (iguales)
        $manillaId = self::obtenerManillaEstrechaIdPorColor($colorId);
        if ($manillaId) {
            $manilla = ProductoColorProveedor::with('producto')->find($manillaId);
            $materiales[] = [
                'producto_id' => $manilla->producto_id,
                'nombre' => $manilla->producto->nombre,
                'unidad' => 'unidad',
                'cantidad' => $cantidad,
                'costo_unitario' => round($manilla->costo),
                'costo_total' => round($manilla->costo * $cantidad),
                'proveedor' => self::buscarNombreProveedor($manilla->producto, null, $manilla->proveedor_id),
            ];
        }

        $materiales[] = self::crearHerraje($productos[44], $alto, $ancho, ceil((((($alto * $ancho) / 10000) * 2) * 0.7) / 300 + 1) * $cantidad, $colorId);
        $materiales[] = self::crearHerraje($productos[40], $alto, $ancho, ceil((($alto / 250) * 2 + ($ancho / 250) * 2) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[41], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[42], $alto, $ancho, ceil((($alto * 2) / 500 + ($ancho * 2) / 500) * $cantidad), $colorId);
        $materiales[] = self::crearHerraje($productos[43], $alto, $ancho, self::calcularCantidadTapaDesague($ancho) * $cantidad, $colorId);

        $costoTotal = array_sum(array_column($materiales, 'costo_total'));
        $costoUnitario = $cantidad > 0 ? $costoTotal / $cantidad : 0;

        return [
            'materiales' => $materiales,
            'costo_total' => round($costoTotal),
            'costo_unitario' => round($costoUnitario),
        ];
    }




// ✅ NUEVO MÉTODO: Calcular Bay Window
protected static function calcularBayWindow(array $ventana): array
{
    Log::info("🏠 Calculando Bay Window - Parámetros recibidos:", $ventana);
    
    $alto = $ventana['alto'];
    $colorId = $ventana['color'];
    $cantidad = $ventana['cantidad'] ?? 1;
    $tipoVidrioId = $ventana['tipoVidrio'] ?? 2;
    $productoVidrioId = $ventana['productoVidrio'];
    $proveedorVidrioId = $ventana['proveedorVidrio'];
    
    // ✅ Datos específicos de Bay Window del frontend
    $anchoIzquierda = $ventana['ancho_izquierda'] ?? 0;
    $anchoCentro = $ventana['ancho_centro'] ?? 0;
    $anchoDerecha = $ventana['ancho_derecha'] ?? 0;
    
    $tipoVentanaIzquierda = $ventana['tipoVentanaIzquierda'] ?? null;
    $tipoVentanaCentro = $ventana['tipoVentanaCentro'] ?? null;
    $tipoVentanaDerecha = $ventana['tipoVentanaDerecha'] ?? null;
    
    Log::info("🏠 Configuración Bay Window", [
        'alto' => $alto,
        'anchos' => [$anchoIzquierda, $anchoCentro, $anchoDerecha],
        'tipos' => [$tipoVentanaIzquierda, $tipoVentanaCentro, $tipoVentanaDerecha],
        'cantidad' => $cantidad
    ]);
    
    $materialesTotal = [];
    $costoTotal = 0;
    
    // ✅ Calcular cada sección por separado
    $secciones = [
        'izquierda' => [
            'ancho' => $anchoIzquierda,
            'config' => $tipoVentanaIzquierda,
            'nombre' => 'Ventana Izquierda'
        ],
        'centro' => [
            'ancho' => $anchoCentro,
            'config' => $tipoVentanaCentro,
            'nombre' => 'Ventana Centro'
        ],
        'derecha' => [
            'ancho' => $anchoDerecha,
            'config' => $tipoVentanaDerecha,
            'nombre' => 'Ventana Derecha'
        ]
    ];
    
    foreach ($secciones as $posicion => $seccion) {
        if ($seccion['ancho'] > 0 && $seccion['config']) {
            Log::info("🔧 Procesando sección {$posicion}", $seccion);
            
            $resultadoSeccion = self::calcularSeccionBayWindow(
                $ventana,
                $seccion,
                $alto,
                $cantidad,
                $posicion
            );
            
            // Agregar prefijo al nombre para identificar la sección
            foreach ($resultadoSeccion['materiales'] as &$material) {
                $material['nombre'] = "[{$seccion['nombre']}] " . $material['nombre'];
            }
            
            $materialesTotal = array_merge($materialesTotal, $resultadoSeccion['materiales']);
            $costoTotal += $resultadoSeccion['costo_total'];
            
            Log::info("💰 Sección {$posicion} calculada", [
                'costo' => $resultadoSeccion['costo_total'],
                'materiales_count' => count($resultadoSeccion['materiales'])
            ]);
        }
    }
    
    Log::info("💰 Bay Window calculada - TOTAL", [
        'costo_total' => $costoTotal,
        'secciones_calculadas' => count(array_filter($secciones, fn($s) => $s['ancho'] > 0)),
        'materiales_count_total' => count($materialesTotal)
    ]);
    
    return [
        'materiales' => $materialesTotal,
        'costo_total' => round($costoTotal),
        'costo_unitario' => round($costoTotal / max($cantidad, 1)),
    ];
}

// ✅ Procesar cada sección individual
protected static function calcularSeccionBayWindow($ventanaOriginal, $seccion, $alto, $cantidad, $posicion)
{
    $config = $seccion['config'];
    
    // ✅ Si es ventana compuesta (tiene partes)
    if (isset($config['compuesta']) && $config['compuesta'] && isset($config['partes'])) {
        return self::calcularVentanaCompuestaBay($ventanaOriginal, $seccion, $alto, $cantidad, $posicion);
    }
    
    // ✅ Si es ventana simple
    return self::calcularVentanaSimpleBay($ventanaOriginal, $seccion, $alto, $cantidad, $posicion);
}

// ✅ Calcular ventana simple de Bay Window
protected static function calcularVentanaSimpleBay($ventanaOriginal, $seccion, $alto, $cantidad, $posicion)
{
    $config = $seccion['config'];
    
    // ✅ Construir ventana individual para calcular
    $ventanaIndividual = [
        'tipo' => $config['partes'][0]['tipo'] ?? $config['tipo'] ?? 2,
        'alto' => $alto,
        'ancho' => $seccion['ancho'],
        'color' => $ventanaOriginal['color'],
        'productoVidrio' => $ventanaOriginal['productoVidrio'],
        'proveedorVidrio' => $ventanaOriginal['proveedorVidrio'],
        'tipoVidrio' => $ventanaOriginal['tipoVidrio'] ?? 2,
        'cantidad' => $cantidad,
    ];
    
    // ✅ Agregar propiedades específicas según el tipo
    if (isset($config['direccionApertura'])) {
        $ventanaIndividual['direccionApertura'] = $config['direccionApertura'];
    }
    
    if (isset($config['ladoApertura'])) {
        $ventanaIndividual['ladoApertura'] = $config['ladoApertura'];
    }
    
    if (isset($config['hojas_totales'])) {
        $ventanaIndividual['hojas_totales'] = $config['hojas_totales'];
        $ventanaIndividual['hojas_moviles'] = $config['hojas_moviles'] ?? $config['hojas_totales'];
        $ventanaIndividual['hojaMovilSeleccionada'] = $config['hojaMovilSeleccionada'] ?? 1;
        $ventanaIndividual['hoja1AlFrente'] = $config['hoja1AlFrente'] ?? true;
    }
    
    Log::info("🔧 Calculando ventana simple {$posicion}", $ventanaIndividual);
    
    // ✅ Usar el método normal de cálculo
    return self::calcularMateriales($ventanaIndividual);
}

// ✅ Calcular ventana compuesta de Bay Window
protected static function calcularVentanaCompuestaBay($ventanaOriginal, $seccion, $alto, $cantidad, $posicion)
{
    $config = $seccion['config'];
    $partes = $config['partes'] ?? [];
    
    $materialesTotal = [];
    $costoTotal = 0;
    
    foreach ($partes as $index => $parte) {
        $nombreParte = $index === 0 ? 'Superior' : 'Inferior';
        
        $ventanaParte = [
            'tipo' => $parte['tipo'],
            'alto' => $parte['alto'],
            'ancho' => $seccion['ancho'],
            'color' => $ventanaOriginal['color'],
            'productoVidrio' => $ventanaOriginal['productoVidrio'],
            'proveedorVidrio' => $ventanaOriginal['proveedorVidrio'],
            'tipoVidrio' => $ventanaOriginal['tipoVidrio'] ?? 2,
            'cantidad' => $cantidad,
        ];
        
        // Agregar propiedades específicas de la parte
        if (isset($parte['direccionApertura'])) {
            $ventanaParte['direccionApertura'] = $parte['direccionApertura'];
        }
        
        if (isset($parte['ladoApertura'])) {
            $ventanaParte['ladoApertura'] = $parte['ladoApertura'];
        }
        
        if (isset($parte['hojas_totales'])) {
            $ventanaParte['hojas_totales'] = $parte['hojas_totales'];
            $ventanaParte['hojas_moviles'] = $parte['hojas_moviles'] ?? $parte['hojas_totales'];
        }
        
        Log::info("🔧 Calculando parte {$nombreParte} de {$posicion}", $ventanaParte);
        
        $resultadoParte = self::calcularMateriales($ventanaParte);
        
        // Agregar prefijo al nombre de materiales
        foreach ($resultadoParte['materiales'] as &$material) {
            $material['nombre'] = "[$nombreParte] " . $material['nombre'];
        }
        
        $materialesTotal = array_merge($materialesTotal, $resultadoParte['materiales']);
        $costoTotal += $resultadoParte['costo_total'];
    }
    
    return [
        'materiales' => $materialesTotal,
        'costo_total' => $costoTotal,
    ];
}

    protected static function calcularCorrederaAndes(array $ventana): array //revisar manillas si estan o no..
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
            98,
            94,
            101,
            103,
            104,
            51,
            105,
            106,
            107,
            108,
            109,
            110,
            111,
            112,
            113,
            114,
            115,
            $idJunquillo,
            $productoVidrioId
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
        $addLinea(94, 4 * $cantidad, (($ancho / 2) - 88 + 16 + 33 + 5));
        $addLinea(94, 4 * $cantidad, $alto - 44 - 44 + 20 + 5);
        $addLinea($idJunquillo, 4 * $cantidad, (($ancho / 2) - 88 + 16 + 33 + 5) - 109);
        $addLinea($idJunquillo, 4 * $cantidad, ($alto - 44 - 44 + 20 + 5) - 109);
        $addLinea(101, 2 * $cantidad, ($alto - 44 - 44 + 20 + 5 - 6));
        $addLinea(103, 4 * $cantidad, (($ancho / 2) - 88 + 16 + 33 + 5) - 117);
        $addLinea(103, 4 * $cantidad, ($alto - 44 - 44 + 20 + 5) - 117);
        $addLinea(104, 2 * $cantidad, $ancho + 5 - 84);
        $addLinea(104, 2 * $cantidad, $alto + 5 - 84);
        $addLinea(51, 2 * $cantidad, $ancho - 44 - 44 - 1);

        $anchoVidrio = ($ancho / 2) - 88 + 16 + 33 + 5 - 109 - 6;
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
            [400, 716, 111],
            [717, 916, 112],
            [917, 1116, 113],
            [1117, 1316, 105],
            [1317, 1516, 106],
            [1517, 1716, 107],
            [1717, 1916, 108],
            [1917, 2116, 109],
            [2117, 2316, 110]
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

    protected static function calcularCorrederaMonorriel(array $ventana): array //revisar manillas si estan o no..
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
            99,
            94,
            100,
            101,
            103,
            104,
            51,
            105,
            106,
            107,
            108,
            109,
            110,
            111,
            112,
            113,
            114,
            115,
            $idJunquillo,
            $productoVidrioId
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

        $addLinea(99, 2 * $cantidad, $ancho + 5);
        $addLinea(99, 2 * $cantidad, $alto + 5);
        $addLinea(94, 2 * $cantidad, (($ancho / 2) - 44 + 8 + 33 + 5));
        $addLinea(94, 2 * $cantidad, $alto - 44 - 44 + 20 + 5);
        $addLinea(94, 1 * $cantidad, $alto - 36 - 36 + 4);
        $addLinea($idJunquillo, 2 * $cantidad, ((($ancho / 2) - 44 + 8 + 33 + 5)) - 109);
        $addLinea($idJunquillo, 2 * $cantidad, ($alto - 44 - 44 + 20 + 5 - 109));
        $addLinea($idJunquillo, 2 * $cantidad, (($ancho / 2) - 36 - 19));
        $addLinea($idJunquillo, 2 * $cantidad, $alto - 37 - 37);
        $addLinea(101, 2 * $cantidad, (($ancho / 2) - 44 + 8 + 33 + 5) - 117); //traslapo
        $addLinea(103, 2 * $cantidad, (($ancho - 88 + 16) / 2 + 33 + 5) - 117); //refuerzo hoja andes
        $addLinea(103, 2 * $cantidad, ($alto - 44 - 44 + 20 + 5) - 117); //refuerzo hoja andes
        $addLinea(103, 1 * $cantidad, $alto - 36 - 36 + 4 - 80); //refuerzo hoja andes
        $addLinea(104, 2 * $cantidad, $ancho + 5 - 84); //refuerzo marco
        $addLinea(104, 2 * $cantidad, $alto + 5 - 84); //Refuerzo marco
        $addLinea(51, 2 * $cantidad, $ancho - 44 - 44 - 1); //riel aluminio
        $addLinea(100, 2 * $cantidad, ($ancho / 2) - 36 - 36); //tapa
        $addLinea(100, 1 * $cantidad, $alto - 36 - 36); // tapa


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
            'cantidad' => 2 * $cantidad,
            'costo_unitario' => round($costoCarro),
            'costo_total' => round($costoCarro * 2 * $cantidad),
            'proveedor' => self::buscarNombreProveedor($carro, $colorId),
        ];

        $cremonaId = null;
        $ranges = [
            [400, 716, 111],
            [717, 916, 112],
            [917, 1116, 113],
            [1117, 1316, 105],
            [1317, 1516, 106],
            [1517, 1716, 107],
            [1717, 1916, 108],
            [1917, 2116, 109],
            [2117, 2316, 110]
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
            $match = $producto->coloresPorProveedor->first(
                fn($item) =>
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
