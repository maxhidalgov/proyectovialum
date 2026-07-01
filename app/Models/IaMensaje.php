<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IaMensaje extends Model
{
    protected $table = 'ia_mensajes';

    protected $fillable = [
        'rol', 'contenido', 'acciones_ejecutadas', 'origen',
    ];

    protected $casts = [
        'acciones_ejecutadas' => 'array',
    ];
}
