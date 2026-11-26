<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ventana extends Model
{
    use HasFactory;

    protected $fillable = [
        'cotizacion_id',
        'tipo_ventana_id',
        'ancho',
        'alto',
        'color_id',
        'producto_vidrio_proveedor_id',
        'imagen',
        'costo',
        'precio',
        'hojas_totales',
        'hojas_moviles',
        'hoja_movil_seleccionada',
        'hoja1_al_frente',
        'cantidad',
        'costo_unitario',
        'precio_unitario',
        'config',
        // Bay Window
        'ancho_izquierda',
        'ancho_centro',
        'ancho_derecha',
        'tipo_ventana_izquierda',
        'tipo_ventana_centro',
        'tipo_ventana_derecha',
    ];

    protected $casts = [
        'costo'                   => 'decimal:2',
        'precio'                  => 'decimal:2',
        'costo_unitario'          => 'decimal:2',
        'precio_unitario'         => 'decimal:2',
        'config'                  => 'array',
        'hoja1_al_frente'         => 'boolean',
        // Bay Window JSON columns — auto-decoded to arrays on read
        'tipo_ventana_izquierda'  => 'array',
        'tipo_ventana_centro'     => 'array',
        'tipo_ventana_derecha'    => 'array',
    ];

    /*
     * CONVENCIÓN DE PRECIOS (definida 2026-11-26):
     *   precio / costo                  = TOTAL de la línea (ya incluye la cantidad)
     *   precio_unitario / costo_unitario = total / cantidad (valor por unidad)
     * Los unitarios son un dato DERIVADO: se recalculan en cada guardado en que cambian precio,
     * costo, cantidad o los propios unitarios, así ningún escritor (cotizador, agente, duplicar…)
     * puede dejarlos inconsistentes. Un guardado que no toca estos campos (p. ej. solo la imagen)
     * no los modifica.
     */
    protected static function booted(): void
    {
        static::saving(function (Ventana $v) {
            if (!$v->exists || $v->isDirty(['precio', 'costo', 'cantidad', 'precio_unitario', 'costo_unitario'])) {
                $v->precio_unitario = self::unitario($v->precio, $v->cantidad);
                $v->costo_unitario  = self::unitario($v->costo, $v->cantidad);
            }
        });
    }

    /** Valor por unidad de un total de línea (entero en pesos). Cantidad nula o 0 cuenta como 1. */
    public static function unitario($total, $cantidad): int
    {
        $cant = max((int) ($cantidad ?? 1), 1);
        return (int) round(((float) $total) / $cant);
    }

    // Lectura de filas antiguas (anteriores a esta regla) con unitario nulo o 0: se deriva sin tocar la BD.
    public function getPrecioUnitarioAttribute($value)
    {
        return ($value !== null && (float) $value > 0) ? $value : self::unitario($this->attributes['precio'] ?? 0, $this->attributes['cantidad'] ?? 1);
    }

    public function getCostoUnitarioAttribute($value)
    {
        return ($value !== null && (float) $value > 0) ? $value : self::unitario($this->attributes['costo'] ?? 0, $this->attributes['cantidad'] ?? 1);
    }

    // ✅ Relación con TipoVentana
    public function tipoVentana()
    {
        return $this->belongsTo(TipoVentana::class, 'tipo_ventana_id');
    }

    // ✅ También define esta relación para que funcione con snake_case
    public function tipo_ventana()
    {
        return $this->belongsTo(TipoVentana::class, 'tipo_ventana_id');
    }

    // Relación con Cotización
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    // Relación con Color
    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    // Relación con ProductoVidrioProveedor
    public function productoVidrioProveedor()
    {
        return $this->belongsTo(ProductoColorProveedor::class, 'producto_vidrio_proveedor_id');
    }

    // Relación con materiales (si tienes tabla pivot)
    public function materiales()
    {
        return $this->belongsToMany(Producto::class, 'ventana_materiales')
                    ->withPivot('cantidad', 'costo_unitario', 'costo_total')
                    ->withTimestamps();
    }
}
