<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoVentana extends Model
{
    protected $table = 'tipos_ventana';

    protected $fillable = ['nombre', 'material_id'];

    public function material()
    {
        return $this->belongsTo(TipoMaterial::class, 'material_id');
    }
}