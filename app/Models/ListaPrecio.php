<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaPrecio extends Model
{
    protected $table = 'lista_precios';

    protected $fillable = [
        'producto_id',
        'producto_color_proveedor_id',
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

    // Relación con ProductoColorProveedor
    public function productoColorProveedor()
    {
        return $this->belongsTo(ProductoColorProveedor::class, 'producto_color_proveedor_id');
    }

    // Relación con CotizacionDetalle
    public function cotizacionDetalles()
    {
        return $this->hasMany(CotizacionDetalle::class, 'lista_precio_id');
    }
}
