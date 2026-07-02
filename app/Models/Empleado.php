<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $fillable = [
        'nombre', 'rut', 'cargo', 'sueldo_base', 'fecha_ingreso',
        'fecha_egreso', 'activo', 'banco', 'cuenta_bancaria', 'tipo_cuenta', 'notas',
        'workera_code', 'telefono',
    ];

    protected $casts = [
        'sueldo_base'  => 'decimal:2',
        'fecha_ingreso' => 'date',
        'fecha_egreso'  => 'date',
        'activo'        => 'boolean',
    ];

    public function pagos()
    {
        return $this->hasMany(PagoEmpleado::class);
    }

    public function pagosMes(string $periodo)
    {
        return $this->pagos()->where('periodo', $periodo)->get();
    }
}
