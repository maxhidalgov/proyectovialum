<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ventana extends Model
{
    protected $fillable = [
    'cotizacion_id',
    'tipo_ventana_id',
    'ancho',
    'alto',
    'cantidad',
    'color_id',
    'tipo_vidrio_id',
    'producto_vidrio_proveedor_id',
    'costo',
    'precio',
    'costo_unitario',
    'precio_unitario',
    'hojas_totales',
    'hojas_moviles',
    'imagen',

    
    ];
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function tipoVentana()
    {
        return $this->belongsTo(TipoVentana::class, 'tipo_ventana_id');
    }

        public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function productoVidrioProveedor()
    {
        return $this->belongsTo(ProductoColorProveedor::class, 'producto_vidrio_proveedor_id');
    }
}
