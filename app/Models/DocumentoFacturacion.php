<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoFacturacion extends Model
{
    protected $table = 'documentos_facturacion';
    protected $fillable = [
        'cotizacion_id', 'tipo', 'porcentaje', 'monto', 'neto', 'estado',
        'id_documento_bsale', 'numero_documento_bsale', 'url_pdf_bsale',
        'fecha_emision', 'nota',
        'tipo_documento_bsale_id', 'forma_pago',
        'cliente_id', 'bsale_cliente_rut', 'bsale_cliente_nombre',
        'nro_comprobante_transbank', 'pagado_con_tarjeta', 'payment_type_id',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }
}
