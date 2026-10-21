<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Boleta o factura enviada al cliente por correo, con el PDF adjunto. */
class DocumentoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tipo,          // "Boleta", "Factura"…
        public ?string $folio,
        public ?string $fecha,        // dd-mm-yyyy
        public string $cuerpo,
        public string $pdfContent,
        public string $nombreArchivo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trim("{$this->tipo} " . ($this->folio ? "N° {$this->folio} " : '') . '· Vialum'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.documento',
            with: [
                'tipo'   => $this->tipo,
                'folio'  => $this->folio,
                'fecha'  => $this->fecha,
                'cuerpo' => $this->cuerpo,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->nombreArchivo)->withMime('application/pdf'),
        ];
    }
}
