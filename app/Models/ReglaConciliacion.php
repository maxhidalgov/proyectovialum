<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReglaConciliacion extends Model
{
    protected $table = 'reglas_conciliacion';

    protected $fillable = [
        'nombre', 'patron', 'categoria', 'tipo', 'prioridad', 'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    /** Aplica las reglas activas a un texto de descripción y tipo de movimiento. */
    public static function categorizar(string $descripcion, string $tipo): ?string
    {
        $reglas = static::where('activa', true)
            ->where(fn($q) => $q->where('tipo', 'A')->orWhere('tipo', $tipo))
            ->orderBy('prioridad')
            ->get();

        foreach ($reglas as $regla) {
            if (stripos($descripcion, $regla->patron) !== false) {
                return $regla->categoria;
            }
        }

        return null;
    }
}
