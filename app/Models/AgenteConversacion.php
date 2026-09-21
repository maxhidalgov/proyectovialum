<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgenteConversacion extends Model
{
    protected $table = 'agente_conversaciones';

    protected $fillable = [
        'canal', 'identificador', 'nombre_contacto', 'estado',
        'bot_activo', 'cliente_id', 'lead_id', 'historial',
    ];

    protected $casts = [
        'bot_activo' => 'boolean',
        'historial'  => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
