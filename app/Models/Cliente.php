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
    ];
}