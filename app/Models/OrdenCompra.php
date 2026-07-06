<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';

    protected $fillable = [
        'numero', 'cotizacion_id', 'proveedor_id', 'proveedor_nombre',
        'observaciones', 'items', 'estado', 'created_by',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
