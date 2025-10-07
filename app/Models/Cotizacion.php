<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';
    protected $fillable = [
        'cliente_id',
        'cliente_facturacion_id',
        'vendedor_id',
        'fecha',
        'estado_cotizacion_id',
        'observaciones',
        'total',
        'origen_id',
        'numero_documento_bsale',
        'id_documento_bsale',
        'fecha_documento_bsale',
        'estado_facturacion',
        'url_pdf_bsale',
        'token_bsale',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function clienteFacturacion()
    {
        return $this->belongsTo(Cliente::class, 'cliente_facturacion_id');
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class);
    }

    public function ventanas()
    {
        return $this->hasMany(Ventana::class);
    }
    public function estado()
{
    return $this->belongsTo(EstadoCotizacion::class, 'estado_cotizacion_id');
}

    public function cotizacionDetalles()
    {
        return $this->hasMany(CotizacionDetalle::class);
    }
}
