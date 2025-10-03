<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoVentana extends Model
{
    use HasFactory;

    protected $table = 'tipos_ventana'; // ✅ Ajustar según tu tabla

    protected $fillable = [
        'nombre',
        'material_id',
        'descripcion'
    ];

    // Relación inversa con Ventana
    public function ventanas()
    {
        return $this->hasMany(Ventana::class, 'tipo_ventana_id');
    }

    // Relación con Material si existe
    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}