<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoItem extends Model
{
    protected $table = 'documento_items';

    protected $fillable = [
        'documento_facturacion_id', 'producto_id', 'nombre', 'cantidad',
        'precio_unitario', 'descuento', 'total_neto',
        'es_vidrio', 'ancho', 'alto', 'piezas', 'pulido',
    ];

    protected $casts = [
        'cantidad'  => 'decimal:4',
        'descuento' => 'decimal:2',
        'es_vidrio' => 'boolean',
        'pulido'    => 'boolean',
    ];

    public function documento()
    {
        return $this->belongsTo(DocumentoFacturacion::class, 'documento_facturacion_id');
    }
}
