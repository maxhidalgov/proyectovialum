<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'producto_id',
        'color_id',
        'cantidad',
        'tipo',
        'referencia_tipo',
        'referencia_id',
        'nota',
        'user_id',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }
}
