<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;
    protected $table = 'productos'; //
    // ✅ Permitir la asignación masiva de estos atributos
    protected $fillable = [
        'nombre',
        //'tipo',
        'tipo_producto_id', // ← ¡esto debe estar incluido!
        'unidad_id',
        'largo_total',
        'peso_por_metro',
        'codigo_proveedor',
    ];

    // ✅ Relación principal: Colores por proveedor
    public function coloresPorProveedor()
    {
        return $this->hasMany(ProductoColorProveedor::class, 'producto_id')
        ->with(['color', 'proveedor']);
    }
    public function unidad()
    {
        return $this->belongsTo(Unidad::class);
    }

    public function productoColor()
    {
        return $this->hasMany(ProductoColorProveedor::class);
    }

    public function tipoProducto()
    {
        return $this->belongsTo(TipoProducto::class, 'tipo_producto_id');
    }

    public function listaPrecios()
    {
        return $this->hasMany(ListaPrecio::class, 'producto_id');
    }

    public static function boot()
{
    parent::boot();

    static::deleting(function ($producto) {
        $producto->coloresPorProveedor()->delete();
    });
}
} 
