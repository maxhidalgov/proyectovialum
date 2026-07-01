<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenteProduccion extends Model
{
    protected $table = 'incidentes_produccion';

    protected $fillable = [
        'cotizacion_id', 'descripcion', 'tipo', 'estado',
        'accion_requerida', 'empleado_responsable_id',
        'fecha_limite_resolucion', 'fecha_resuelto',
    ];

    protected $casts = [
        'fecha_limite_resolucion' => 'date',
        'fecha_resuelto'          => 'date',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function empleadoResponsable()
    {
        return $this->belongsTo(Empleado::class, 'empleado_responsable_id');
    }
}
