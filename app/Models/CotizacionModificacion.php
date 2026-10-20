<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cambio de oferta o ajuste de precio de una cotización ya aprobada (totales en NETO). */
class CotizacionModificacion extends Model
{
    protected $table = 'cotizacion_modificaciones';

    protected $fillable = [
        'cotizacion_id', 'tipo', 'oferta_anterior', 'oferta_nueva',
        'total_anterior', 'total_nuevo', 'motivo', 'usuario_id',
    ];

    protected $casts = [
        'total_anterior' => 'float',
        'total_nuevo'    => 'float',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }
}
