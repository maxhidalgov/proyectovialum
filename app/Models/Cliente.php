<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = [
        'bsale_id',
        'tipo_cliente',
        'first_name',
        'last_name',
        'email',
        'identification',
        'phone',
        'address',
        'razon_social',
        'giro',
        'ciudad',
        'comuna',
        'descuento_productos',
    ];

    protected $casts = [
        'descuento_productos' => 'decimal:2',
    ];

    public function cotizaciones()
    {
        return $this->hasMany(\App\Models\Cotizacion::class, 'cliente_id');
    }
}