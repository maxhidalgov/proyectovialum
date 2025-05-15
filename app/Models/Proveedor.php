<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedors'; // ✅ Definir el nombre correcto de la tabla

    protected $fillable = ['nombre', 'contacto']; // ✅ Habilitar asignación masiva


public function productosPorColor()
{
    return $this->hasMany(ProductoColorProveedor::class);
}
}