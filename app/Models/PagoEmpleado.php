<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoEmpleado extends Model
{
    protected $table = 'pagos_empleado';

    protected $fillable = [
        'empleado_id', 'movimiento_id', 'periodo', 'monto',
        'tipo', 'pagado', 'fecha_pago', 'notas',
    ];

    protected $casts = [
        'periodo'    => 'date',
        'fecha_pago' => 'date',
        'monto'      => 'decimal:2',
        'pagado'     => 'boolean',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function movimiento()
    {
        return $this->belongsTo(MovimientoBancario::class, 'movimiento_id');
    }
}
