<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraItem extends Model
{
    protected $fillable = [
        'compra_id',
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
}
