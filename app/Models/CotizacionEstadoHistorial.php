<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionEstadoHistorial extends Model
{
    protected $table = 'cotizacion_estado_historial';

    protected $fillable = [
        'cotizacion_id', 'tipo', 'estado', 'estado_anterior', 'fecha',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }
}
