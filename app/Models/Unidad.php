<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    protected $table = 'unidades'; // importante si no se llama 'unidads'
    
    protected $fillable = [
        'nombre',
        'requiere_division',
        'descripcion'
    ];
}