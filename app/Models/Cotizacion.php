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
        'adjunto_winperfil',
        'pedido_proveedor',
        'estado_produccion',
        'fecha_entrega',
        'notas_operaciones',
        'winperfil_numero',
        'winperfil_serie',
        'winperfil_synced_at',
        'winperfil_precio_lock',
        'fecha_entrega_real',
        'tipo_vidrio',
        'fabricar_termopanel',
        'cortar_vidrio_cnc',
    ];

    protected $casts = [
        'pedido_proveedor'      => 'boolean',
        'winperfil_precio_lock' => 'boolean',
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

    public function etapas()
    {
        return $this->hasMany(EtapaProduccion::class);
    }

    public function incidentes()
    {
        return $this->hasMany(IncidenteProduccion::class);
    }

    public function visitas()
    {
        return $this->hasMany(VisitaCliente::class);
    }
    public function estado()
{
    return $this->belongsTo(EstadoCotizacion::class, 'estado_cotizacion_id');
}

    public function cotizacionDetalles()
    {
        return $this->hasMany(CotizacionDetalle::class);
    }

    public function detalles()
    {
        return $this->hasMany(CotizacionDetalle::class);
    }

    public function abonos()
    {
        return $this->hasMany(Abono::class);
    }

    public function documentosFacturacion()
    {
        return $this->hasMany(DocumentoFacturacion::class);
    }

    public function historialEstados()
    {
        return $this->hasMany(CotizacionEstadoHistorial::class);
    }

    /**
     * Registrar automáticamente cada cambio de estado (producción o comercial)
     * en el historial, sin importar desde qué controlador se haga el cambio.
     */
    protected static function booted(): void
    {
        static::updated(function (Cotizacion $c) {
            if ($c->wasChanged('estado_produccion')) {
                $c->historialEstados()->create([
                    'tipo'            => 'produccion',
                    'estado'          => $c->estado_produccion,
                    'estado_anterior' => $c->getOriginal('estado_produccion'),
                    'fecha'           => now(),
                ]);
            }

            if ($c->wasChanged('estado_cotizacion_id')) {
                $c->historialEstados()->create([
                    'tipo'            => 'comercial',
                    'estado'          => optional(EstadoCotizacion::find($c->estado_cotizacion_id))->nombre,
                    'estado_anterior' => optional(EstadoCotizacion::find($c->getOriginal('estado_cotizacion_id')))->nombre,
                    'fecha'           => now(),
                ]);
            }
        });

        static::created(function (Cotizacion $c) {
            if ($c->estado_produccion) {
                $c->historialEstados()->create([
                    'tipo'   => 'produccion',
                    'estado' => $c->estado_produccion,
                    'fecha'  => now(),
                ]);
            }
        });
    }
}
