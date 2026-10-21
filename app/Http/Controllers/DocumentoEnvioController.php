<?php

namespace App\Http\Controllers;

use App\Mail\DocumentoMail;
use App\Models\DocumentoFacturacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Enviar boletas y facturas al cliente (módulo Boletas/Facturas): el PDF viaja como ARCHIVO,
 * por correo (adjunto) o compartido por WhatsApp. El PDF es el oficial de Bsale (con timbre).
 */
class DocumentoEnvioController extends Controller
{
    private const TIPOS = [1 => 'Boleta', 2 => 'Nota de crédito', 5 => 'Factura'];

    private function tipoNombre(DocumentoFacturacion $doc): string
    {
        return self::TIPOS[(int) $doc->tipo_documento_bsale_id] ?? 'Documento';
    }

    private function nombreArchivo(DocumentoFacturacion $doc): string
    {
        return $this->tipoNombre($doc) . '-' . ($doc->numero_documento_bsale ?: $doc->id) . '-Vialum.pdf';
    }

    /** GET /api/ventas/documento/{id}/pdf → el PDF como archivo (lo usa el navegador para compartir por WhatsApp). */
    public function pdf($id)
    {
        $doc = DocumentoFacturacion::findOrFail($id);
        $bytes = app(BsaleController::class)->pdfBytesDocumento($doc);
        if (!$bytes) {
            return response()->json(['message' => 'No se pudo obtener el PDF de este documento.'], 404);
        }

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->nombreArchivo($doc) . '"',
        ]);
    }

    /**
     * POST /api/ventas/documento/{id}/enviar
     *   via=email     + email [+ mensaje]  → envía el correo con el PDF adjunto
     *   via=whatsapp  + solo_registrar     → deja registrado que se compartió el PDF por WhatsApp
     */
    public function enviar(Request $request, $id)
    {
        $data = $request->validate([
            'via'            => 'required|in:email,whatsapp',
            'email'          => 'nullable|email|max:150',
            'telefono'       => 'nullable|string|max:30',
            'mensaje'        => 'nullable|string|max:2000',
            'solo_registrar' => 'nullable|boolean',
        ]);

        $doc = DocumentoFacturacion::findOrFail($id);

        if ($data['via'] === 'whatsapp') {
            $this->registrar($doc, 'whatsapp', ($data['telefono'] ?? null) ?: 'PDF compartido');
            return response()->json(['ok' => true, 'ultimo_envio_at' => now()->toDateTimeString(), 'ultimo_envio_via' => 'whatsapp', 'ultimo_envio_a' => ($data['telefono'] ?? null) ?: 'PDF compartido']);
        }

        // Con el driver "log"/"array" el envío "funciona" pero no llega a nadie: se avisa
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return response()->json(['message' => 'El correo no está configurado en el servidor (falta el SMTP).'], 422);
        }
        $dest = trim((string) ($data['email'] ?? ''));
        if ($dest === '' || !filter_var($dest, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Ingresa un correo válido para enviar el documento.'], 422);
        }

        $bytes = app(BsaleController::class)->pdfBytesDocumento($doc);
        if (!$bytes) {
            return response()->json(['message' => 'No se pudo obtener el PDF del documento; intenta de nuevo.'], 502);
        }

        $tipo  = $this->tipoNombre($doc);
        $folio = $doc->numero_documento_bsale ? (string) $doc->numero_documento_bsale : null;
        $fecha = $doc->fecha_emision ? \Carbon\Carbon::parse($doc->fecha_emision)->format('d-m-Y') : null;
        $nombre = $this->nombreCliente($doc);
        $cuerpo = trim((string) ($data['mensaje'] ?? '')) ?: (
            ($nombre ? "Hola {$nombre}," : 'Hola,')
            . "\n\nTe enviamos adjunta la " . mb_strtolower($tipo) . ($folio ? " N° {$folio}" : '') . ' de Vialum.'
            . "\n\nMuchas gracias.\n\nSaludos,\nVialum"
        );

        try {
            Mail::to($dest)->send(new DocumentoMail($tipo, $folio, $fecha, $cuerpo, $bytes, $this->nombreArchivo($doc)));
        } catch (\Throwable $e) {
            Log::warning('Documento: no se pudo enviar el correo', ['documento' => $doc->id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'No se pudo enviar el correo. Revisa la configuración de correo (SMTP) en el servidor.',
                'detalle' => $e->getMessage(),
            ], 500);
        }

        // Si el cliente no tenía correo y se escribió uno, se guarda en su ficha
        if ($doc->cliente_id) {
            $cliente = \App\Models\Cliente::find($doc->cliente_id);
            if ($cliente && empty($cliente->email)) {
                $cliente->update(['email' => $dest]);
            }
        }

        $this->registrar($doc, 'email', $dest);

        return response()->json(['ok' => true, 'mensaje' => "Documento enviado a {$dest}", 'ultimo_envio_at' => now()->toDateTimeString(), 'ultimo_envio_via' => 'email', 'ultimo_envio_a' => $dest]);
    }

    private function registrar(DocumentoFacturacion $doc, string $via, string $destino): void
    {
        DB::table('documentos_facturacion')->where('id', $doc->id)->update([
            'ultimo_envio_via' => $via,
            'ultimo_envio_a'   => mb_substr($destino, 0, 150),
            'ultimo_envio_at'  => now(),
        ]);
    }

    private function nombreCliente(DocumentoFacturacion $doc): ?string
    {
        $c = $doc->cliente_id ? \App\Models\Cliente::find($doc->cliente_id) : null;
        $n = $c ? ($c->razon_social ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''))) : ($doc->bsale_cliente_nombre ?: '');
        $n = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $n));

        return ($n === '' || mb_strtolower($n) === 'consumidor final') ? null : $n;
    }
}
