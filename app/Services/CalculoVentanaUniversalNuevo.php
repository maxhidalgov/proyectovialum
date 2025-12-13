<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\Producto;

/**
 * Métodos auxiliares para calcular ventanas del Armador Universal (estructura recursiva)
 * Copiar estos métodos a CalculoVentanaService.php reemplazando calcularVentanaUniversal()
 */
trait CalculoVentanaUniversalNuevo
{
    /**
     * Calcula materiales de una ventana diseñada con el armador universal (estructura recursiva)
     */
    protected static function calcularVentanaUniversal(array $ventana): array
    {
        Log::info("🏗️ Calculando Ventana Universal (Armador Recursivo) - Parámetros recibidos:", $ventana);

        $colorId = $ventana['color'] ?? null;
        $cantidad = $ventana['cantidad'] ?? 1;
        
        // Configuración del armador (estructura recursiva)
        $estructura = $ventana['configuracionArmador'] ?? null;
        
        if (!$estructura) {
            Log::warning("⚠️ No se encontró estructura del armador");
            return [
                'materiales' => [],
                'costo_total' => 0,
                'costo_unitario' => 0,
            ];
        }

        Log::info("🔧 Estructura Armador:", [
            'tipo' => $estructura['tipo'] ?? 'desconocido',
            'ancho' => $estructura['ancho'] ?? 0,
            'alto' => $estructura['alto'] ?? 0,
            'tiene_hijos' => isset($estructura['hijos']),
        ]);

        // Recolectar IDs de productos necesarios
        $productoIds = [];
        self::recolectarProductosRecursivo($estructura, $productoIds);
        
        // Agregar producto de vidrio si existe
        if (!empty($ventana['productoVidrio'])) {
            $productoIds[] = $ventana['productoVidrio'];
        }

        $productoIds = array_unique(array_filter($productoIds));

        if (empty($productoIds)) {
            Log::warning("⚠️ No se encontraron perfiles configurados");
            return [
                'materiales' => [],
                'costo_total' => 0,
                'costo_unitario' => 0,
            ];
        }

        // Cargar productos
        $productos = Producto::with('coloresPorProveedor.proveedor')
            ->whereIn('id', $productoIds)
            ->get()
            ->keyBy('id');

        $materiales = [];

        // Helper para agregar líneas
        $addLinea = function ($id, $cantidadTotal, $largoMm, $descripcion = '') 
            use (&$materiales, $productos, $colorId) {
            if (!isset($productos[$id])) {
                Log::warning("⚠️ Producto no encontrado: {$id}");
                return;
            }

            $producto = $productos[$id];
            $largoMt = $largoMm / 1000;
            $costoBarra = self::buscarCostoPorColor($producto, $colorId);
            $costoPorMetro = $producto->largo_total > 0 ? $costoBarra / $producto->largo_total : 0;
            $costoTotal = $cantidadTotal * $largoMt * $costoPorMetro;

            $materiales[] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre . ($descripcion ? " ({$descripcion})" : ''),
                'unidad' => 'm',
                'cantidad' => round($cantidadTotal * $largoMt, 3),
                'costo_unitario' => round($costoPorMetro),
                'costo_total' => round($costoTotal),
                'proveedor' => self::buscarNombreProveedor($producto, $colorId),
            ];
        };

        // === CALCULAR MARCO PRINCIPAL ===
        if (!empty($estructura['perfilId'])) {
            $perimetroMarco = 2 * $estructura['ancho'] + 2 * $estructura['alto'];
            $addLinea($estructura['perfilId'], $cantidad, $perimetroMarco, 'Marco principal');
            Log::info("✅ Marco principal: {$perimetroMarco}mm");
        }

        // === PROCESAR ESTRUCTURA RECURSIVAMENTE ===
        self::procesarNodoRecursivo($estructura, $cantidad, $addLinea, $ventana, $colorId, $materiales);

        // === CALCULAR TOTALES ===
        $costoTotal = array_sum(array_column($materiales, 'costo_total'));
        $costoUnitario = $cantidad > 0 ? $costoTotal / $cantidad : 0;

        Log::info("💰 Cálculo finalizado", [
            'materiales' => count($materiales),
            'costo_total' => $costoTotal,
            'costo_unitario' => $costoUnitario,
        ]);

        return [
            'materiales' => $materiales,
            'costo_total' => round($costoTotal),
            'costo_unitario' => round($costoUnitario),
        ];
    }

    /**
     * Recolecta recursivamente todos los IDs de productos usados en la estructura
     */
    protected static function recolectarProductosRecursivo($nodo, &$productoIds)
    {
        // Agregar perfil del nodo actual
        if (!empty($nodo['perfilId'])) {
            $productoIds[] = $nodo['perfilId'];
        }

        // Si tiene hijos, procesar recursivamente
        if (!empty($nodo['hijos'])) {
            // Agregar perfil del divisor
            if (!empty($nodo['hijos']['divisor']['perfilId'])) {
                $productoIds[] = $nodo['hijos']['divisor']['perfilId'];
            }

            // Procesar nodos hijos
            if (!empty($nodo['hijos']['nodo1'])) {
                self::recolectarProductosRecursivo($nodo['hijos']['nodo1'], $productoIds);
            }
            if (!empty($nodo['hijos']['nodo2'])) {
                self::recolectarProductosRecursivo($nodo['hijos']['nodo2'], $productoIds);
            }
        }
    }

    /**
     * Procesa recursivamente cada nodo para calcular divisores y ventanas
     */
    protected static function procesarNodoRecursivo($nodo, $cantidad, $addLinea, $ventana, $colorId, &$materiales)
    {
        // Si el nodo tiene un tipo de ventana asignado, calcular esa ventana
        if (!empty($nodo['tipoVentanaId'])) {
            Log::info("🪟 Procesando ventana tipo {$nodo['tipoVentanaId']} - {$nodo['ancho']}x{$nodo['alto']}mm");
            
            // Crear una ventana temporal para calcular
            $ventanaTemporal = [
                'tipo' => $nodo['tipoVentanaId'],
                'ancho' => $nodo['ancho'],
                'alto' => $nodo['alto'],
                'cantidad' => $cantidad,
                'color' => $colorId,
                'tipoVidrio' => $ventana['tipoVidrio'] ?? null,
                'productoVidrio' => $ventana['productoVidrio'] ?? null,
            ];

            // Calcular materiales de esta ventana
            $resultadoVentana = self::calcularVentana($ventanaTemporal);
            
            // Agregar materiales al array principal
            if (!empty($resultadoVentana['materiales'])) {
                foreach ($resultadoVentana['materiales'] as $material) {
                    $materiales[] = $material;
                }
            }
        }

        // Si tiene hijos (divisiones), procesarlos
        if (!empty($nodo['hijos'])) {
            $hijos = $nodo['hijos'];
            $orientacion = $hijos['orientacion'] ?? 'horizontal';
            $divisor = $hijos['divisor'] ?? [];

            // Calcular largo del divisor
            if (!empty($divisor['perfilId'])) {
                $largoDivisor = $orientacion === 'horizontal' 
                    ? $nodo['ancho'] 
                    : $nodo['alto'];
                
                $addLinea(
                    $divisor['perfilId'], 
                    $cantidad, 
                    $largoDivisor, 
                    "Divisor " . ($orientacion === 'horizontal' ? 'H' : 'V')
                );
                
                Log::info("✅ Divisor {$orientacion}: {$largoDivisor}mm");
            }

            // Procesar nodos hijos recursivamente
            if (!empty($hijos['nodo1'])) {
                self::procesarNodoRecursivo($hijos['nodo1'], $cantidad, $addLinea, $ventana, $colorId, $materiales);
            }
            if (!empty($hijos['nodo2'])) {
                self::procesarNodoRecursivo($hijos['nodo2'], $cantidad, $addLinea, $ventana, $colorId, $materiales);
            }
        }
    }
}
