<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persona de contacto de un cliente (empresa). Las cotizaciones pueden ir dirigidas a ella.
 */
class ClienteContacto extends Model
{
    protected $table = 'cliente_contactos';

    protected $fillable = [
        'cliente_id',
        'nombre',
        'cargo',
        'telefono',
        'email',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
