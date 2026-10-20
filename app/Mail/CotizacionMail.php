<?php

namespace App\Mail;

use App\Models\Cotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Cotización enviada al cliente por correo, con el PDF adjunto (no un link). */
class CotizacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cotizacion $cotizacion,
        public string $cuerpo,
        public string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Cotización #{$this->cotizacion->id} · Vialum",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cotizacion',
            with: [
                'cotizacion' => $this->cotizacion,
                'cuerpo'     => $this->cuerpo,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, "Cotizacion-{$this->cotizacion->id}-Vialum.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
