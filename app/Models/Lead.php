<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $table = 'leads';

    protected $fillable = [
        'nombre', 'telefono', 'email', 'comuna',
        'tipo_producto', 'material', 'tipo_obra',
        'detalle', 'presupuesto_aprox', 'origen', 'estado',
        'cliente_id', 'conversacion_id', 'vendedor_id',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
