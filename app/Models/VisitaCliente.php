<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitaCliente extends Model
{
    protected $table = 'visitas_cliente';

    protected $fillable = [
        'cotizacion_id', 'cliente_id', 'tipo', 'fecha', 'hora', 'estado', 'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function empleados()
    {
        return $this->belongsToMany(Empleado::class, 'visita_empleado');
    }
}
