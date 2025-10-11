<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaPrecio extends Model
{
    protected $table = 'lista_precios';

    protected $fillable = [
        'producto_id',
        'producto_color_proveedor_id', // Mantener por compatibilidad
        'color_id',
        'proveedor_sugerido_id',
        'precio_costo',
        'margen',
        'precio_venta',
        'vigencia_desde',
        'vigencia_hasta',
        'activo'
    ];

    protected $casts = [
        'precio_costo' => 'decimal:2',
        'margen' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
        'activo' => 'boolean'
    ];

    // Relación con Producto
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    // Relación con Color directo
    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    // Relación con Proveedor sugerido
    public function proveedorSugerido()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_sugerido_id');
    }

    // Relación con ProductoColorProveedor (mantener por compatibilidad)
    public function productoColorProveedor()
    {
        return $this->belongsTo(ProductoColorProveedor::class, 'producto_color_proveedor_id');
    }

    // Relación con CotizacionDetalle
    public function cotizacionDetalles()
    {
        return $this->hasMany(CotizacionDetalle::class, 'lista_precio_id');
    }

    /**
     * Calcular el costo máximo entre todos los proveedores de un producto+color
     * Retorna: ['costo_maximo' => float, 'proveedor_id' => int]
     */
    public static function calcularCostoMaximo($producto_id, $color_id)
    {
        $resultado = ProductoColorProveedor::where('producto_id', $producto_id)
            ->where('color_id', $color_id)
            ->orderBy('costo', 'desc')
            ->first(['costo', 'proveedor_id', 'id']);

        if (!$resultado) {
            return [
                'costo_maximo' => 0,
                'proveedor_id' => null,
                'producto_color_proveedor_id' => null
            ];
        }

        return [
            'costo_maximo' => $resultado->costo,
            'proveedor_id' => $resultado->proveedor_id,
            'producto_color_proveedor_id' => $resultado->id
        ];
    }
}
