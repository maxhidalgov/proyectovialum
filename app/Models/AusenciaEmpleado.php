<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AusenciaEmpleado extends Model
{
    protected $table = 'ausencias_empleado';

    protected $fillable = [
        'empleado_id', 'fecha', 'tipo', 'motivo', 'workera_permission_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }
}
