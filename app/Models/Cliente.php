<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'identification',
        'phone',
        'address',
        'tipo_cliente',
        'razon_social',
        'giro',
        'ciudad',
        'comuna',
    ];
}