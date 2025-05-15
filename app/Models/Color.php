<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Color extends Model {
    use HasFactory;

    protected $table = 'colores'; // 👈 Aseguramos que use la tabla correcta

    protected $fillable = ['nombre']; // 👈 Definir los atributos que se pueden asignar masivamente

    public function productos() {
        return $this->belongsToMany(Producto::class, 'producto_color_proveedor', 'color_id', 'producto_id');
    }
}

