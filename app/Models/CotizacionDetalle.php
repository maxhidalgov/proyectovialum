<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionDetalle extends Model
{
    protected $table = 'cotizacion_detalles';
    
    protected $fillable = [
        'cotizacion_id',
        'producto_id',
        'tipo_item',
        'producto_lista_id',
        'lista_precio_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'total',
        // Campos para productos tipo vidrio
        'esVidrio',
        'ancho_mm',
        'alto_mm',
        'm2',
        'pulido'
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'total' => 'decimal:2',
        'esVidrio' => 'boolean',
        'ancho_mm' => 'decimal:2',
        'alto_mm' => 'decimal:2',
        'm2' => 'decimal:4',
        'pulido' => 'boolean'
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    // Producto original del cotizador (puede ser null si es ventana)
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    // Producto de la lista de precios
    public function productoLista()
    {
        return $this->belongsTo(Producto::class, 'producto_lista_id');
    }

    public function listaPrecio()
    {
        return $this->belongsTo(ListaPrecio::class);
    }
}
