<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recordatorio extends Model
{
    protected $table = 'recordatorios';

    protected $fillable = [
        'titulo', 'descripcion', 'fecha', 'hora', 'tipo', 'estado',
        'cotizacion_id', 'cliente_id', 'empleado_id', 'origen', 'completado_at',
    ];

    protected $casts = [
        'fecha'         => 'date',
        'completado_at' => 'datetime',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }
}
