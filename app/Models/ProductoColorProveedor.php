<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductoColorProveedor extends Model
{
    use HasFactory;
    protected $table = 'producto_color_proveedor';

    protected $fillable = [
        'producto_id',
        'proveedor_id',
        'color_id',
        'codigo_proveedor',
        'costo',
        'stock',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function proveedor()
{
    return $this->belongsTo(Proveedor::class);

}

public function color()
{
    return $this->belongsTo(Color::class); // o Color si el modelo se llama así
}
}
