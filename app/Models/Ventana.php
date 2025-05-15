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
        'color_id',
        'producto_vidrio_proveedor_id',
        'costo',
        'precio'
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function tipoVentana()
    {
    return $this->belongsTo(TipoVentana::class);
    }
}
