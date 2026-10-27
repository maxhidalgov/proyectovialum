<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    /** Devuelve el token público, generándolo y guardándolo si aún no existe. */
    public function publicToken(): string
    {
        if (empty($this->public_token)) {
            $this->public_token = Str::random(40);
            $this->save();
        }

        return $this->public_token;
    }
    protected $fillable = [
        'cliente_id',
        'cliente_facturacion_id',
        // Persona de contacto de la empresa a la que va dirigida la cotización
        'contacto_id',
        'contacto_nombre',
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
        'facturacion_cerrada',
        'url_pdf_bsale',
        'token_bsale',
        'public_token',
        'enviado_at',
        'enviado_via',
        'enviado_a',
        'adjunto_winperfil',
        'pedido_proveedor',
        'fecha_pedir_material',
        'estado_produccion',
        'fecha_entrega',
        'notas_operaciones',
        'winperfil_numero',
        'winperfil_serie',
        'winperfil_synced_at',
        'winperfil_precio_lock',
        'winperfil_presupuesto',
        'winperfil_oferta',
        'winperfil_numeros_previos',
        'estado_tecnico',
        'tecnico_nota',
        'fecha_entrega_real',
        'tipo_vidrio',
        'fabricar_termopanel',
        'cortar_vidrio_cnc',
        // Tablero de Operaciones
        'material',
        'estado_obra',
        'postventa',
        'eett',
        'oculto_operaciones',
        // Proyectos manuales (Fase 2)
        'es_manual',
        'nombre_manual',
        'abono_manual',
        'cant_manual',
        'm2_manual',
    ];

    protected $casts = [
        'pedido_proveedor'      => 'boolean',
        'winperfil_precio_lock' => 'boolean',
        'winperfil_numeros_previos' => 'array',
        'postventa'             => 'boolean',
        'es_manual'             => 'boolean',
        'oculto_operaciones'    => 'boolean',
        'facturacion_cerrada'   => 'boolean',
        'enviado_at'            => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function clienteFacturacion()
    {
        return $this->belongsTo(Cliente::class, 'cliente_facturacion_id');
    }

    public function contacto()
    {
        return $this->belongsTo(ClienteContacto::class, 'contacto_id');
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
        // Token público no adivinable para compartir el PDF (link de WhatsApp/correo)
        static::creating(function (Cotizacion $cot) {
            if (empty($cot->public_token)) {
                $cot->public_token = Str::random(40);
            }
        });

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

    public function modificaciones()
    {
        return $this->hasMany(CotizacionModificacion::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Semáforo técnico de un proyecto de Winperfil ya aprobado (qué falta del lado de Winperfil
     * para fabricar con las cantidades exactas). Devuelve ['nivel' => rojo|amarillo|verde, 'texto' => …]
     * o null si no aplica (no es de Winperfil, aún no se aprueba o ya está instalada).
     *   rojo     → venta parcial: falta crear la oferta con solo lo vendido
     *   amarillo → esperando medidas definitivas (con o sin material ya pedido)
     *   verde    → oferta y medidas definitivas
     */
    public function semaforoTecnico(): ?array
    {
        if (!$this->winperfil_numero || $this->es_manual) {
            return null;
        }
        // Solo desde que hay venta: Aprobada, En Producción, Entregada, Facturada
        if (!in_array((int) $this->estado_cotizacion_id, [2, 6, 7, 8], true)) {
            return null;
        }
        $ep = (string) $this->estado_produccion;
        if ($ep === 'Instalada') {
            return null;
        }
        if ($this->estado_tecnico === 'sin_oferta') {
            return ['nivel' => 'rojo', 'texto' => 'Falta crear en Winperfil la oferta con solo lo vendido'];
        }
        $pasoMedidas = in_array($ep, ['Lista para Corte', 'En Fabricación', 'Fabricadas OK'], true);
        if ($this->estado_tecnico === 'definitiva' || $pasoMedidas) {
            return ['nivel' => 'verde', 'texto' => 'Oferta y medidas definitivas'];
        }
        return ['nivel' => 'amarillo', 'texto' => $this->pedido_proveedor
            ? 'Material pedido con medidas provisorias: falta la pauta definitiva'
            : 'Esperando las medidas definitivas'];
    }
}
