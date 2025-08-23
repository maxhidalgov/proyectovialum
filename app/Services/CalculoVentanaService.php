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
        138, 139, // Cremonas
        // Herrajes universales
        40, 41, 42, 43, 44, // tornillos, silicona, tapas
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
        138, 139, // Cremonas (igual que exterior)
        // Herrajes universales (igual que exterior)
        40, 41, 42, 43, 44, // tornillos, silicona, tapas
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
