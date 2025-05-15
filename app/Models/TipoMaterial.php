<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoMaterial extends Model
{
    protected $table = 'tipos_material';

    protected $fillable = ['nombre'];

    public function tiposVentana()
    {
        return $this->hasMany(TipoVentana::class, 'material_id');
    }
}