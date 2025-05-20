<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';
    protected $fillable = [
    'cliente_id',
    'vendedor_id',
    'fecha',
    'estado_cotizacion_id',
    'observaciones',
    'total',
    'origen_id',
];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class);
    }

    public function ventanas()
    {
        return $this->hasMany(Ventana::class);
    }
    public function estado()
{
    return $this->belongsTo(EstadoCotizacion::class, 'estado_cotizacion_id');
}
}
