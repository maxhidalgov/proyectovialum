<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtapaProduccion extends Model
{
    protected $table = 'etapas_produccion';

    protected $fillable = [
        'cotizacion_id', 'etapa', 'estado', 'empleado_id',
        'fecha_inicio', 'fecha_fin_estimada', 'fecha_fin_real', 'notas',
    ];

    protected $casts = [
        'fecha_inicio'        => 'date',
        'fecha_fin_estimada'  => 'date',
        'fecha_fin_real'      => 'date',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }
}
