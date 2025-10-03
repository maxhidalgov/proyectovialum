<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ventana extends Model
{
    use HasFactory;

    protected $fillable = [
        'cotizacion_id',
        'tipo_ventana_id',
        'ancho',
        'alto',
        'color_id',
        'producto_vidrio_proveedor_id',
        'imagen',
        'costo',
        'precio',
        'hojas_totales',
        'hojas_moviles',
        'cantidad',
        'costo_unitario',
        'precio_unitario'
    ];

    protected $casts = [
        'costo' => 'decimal:2',
        'precio' => 'decimal:2',
        'costo_unitario' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
    ];

    // ✅ Relación con TipoVentana
    public function tipoVentana()
    {
        return $this->belongsTo(TipoVentana::class, 'tipo_ventana_id');
    }

    // ✅ También define esta relación para que funcione con snake_case
    public function tipo_ventana()
    {
        return $this->belongsTo(TipoVentana::class, 'tipo_ventana_id');
    }

    // Relación con Cotización
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    // Relación con Color
    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    // Relación con ProductoVidrioProveedor
    public function productoVidrioProveedor()
    {
        return $this->belongsTo(ProductoColorProveedor::class, 'producto_vidrio_proveedor_id');
    }

    // Relación con materiales (si tienes tabla pivot)
    public function materiales()
    {
        return $this->belongsToMany(Producto::class, 'ventana_materiales')
                    ->withPivot('cantidad', 'costo_unitario', 'costo_total')
                    ->withTimestamps();
    }
}
