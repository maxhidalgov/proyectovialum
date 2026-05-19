<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    protected $fillable = [
        'bsale_id',
        'folio',
        'tipo_dte',
        'rut_emisor',
        'nombre_emisor',
        'fecha_emision',
        'fecha_recepcion',
        'neto',
        'iva',
        'total',
        'estado',
        'xml_url',
        'pdf_url',
    ];

    protected $casts = [
        'fecha_emision'   => 'date',
        'fecha_recepcion' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(CompraItem::class);
    }
}
