<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorasExtra extends Model
{
    protected $table = 'horas_extra';

    protected $fillable = [
        'empleado_id', 'cotizacion_id', 'fecha', 'horas', 'descripcion', 'autorizado_workera',
    ];

    protected $casts = [
        'fecha'              => 'date',
        'autorizado_workera' => 'boolean',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }
}
