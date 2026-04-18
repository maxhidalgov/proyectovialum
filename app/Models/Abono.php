<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Abono extends Model
{
    protected $table = 'abonos';
    protected $fillable = ['cotizacion_id', 'monto', 'fecha', 'nota'];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }
}
