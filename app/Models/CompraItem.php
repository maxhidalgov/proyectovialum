<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraItem extends Model
{
    protected $fillable = [
        'compra_id',
        'pcp_id',
        'codigo',
        'nombre',
        'cantidad',
        'unidad',
        'precio_unitario',
        'descuento',
        'total_linea',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function pcp()
    {
        return $this->belongsTo(ProductoColorProveedor::class, 'pcp_id');
    }
}
