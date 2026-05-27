<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngresoManual extends Model
{
    protected $table = 'ingresos_manuales';

    protected $fillable = [
        'fecha',
        'descripcion',
        'monto',
        'categoria',
        'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function movimientos()
    {
        return $this->belongsToMany(
            MovimientoBancario::class,
            'ingreso_movimiento',
            'ingreso_id',
            'movimiento_id'
        )->withPivot('monto')->withTimestamps();
    }
}
