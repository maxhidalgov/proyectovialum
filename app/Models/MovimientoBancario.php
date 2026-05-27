<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoBancario extends Model
{
    protected $table = 'movimientos_bancarios';

    protected $fillable = [
        'chipax_id', 'chipax_cuenta_id',
        'cuenta', 'fecha_contable', 'fecha_valor', 'descripcion', 'glosa',
        'monto', 'tipo', 'numero_documento', 'saldo_disponible',
        'bch_codigo', 'raw', 'compra_id', 'cotizacion_id',
        'categoria', 'conciliado',
    ];

    protected $casts = [
        'fecha_contable'   => 'date',
        'fecha_valor'      => 'date',
        'monto'            => 'decimal:2',
        'saldo_disponible' => 'decimal:2',
        'raw'              => 'array',
        'conciliado'       => 'boolean',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }
}
