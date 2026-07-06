<?php

namespace App\Mail;

use App\Models\OrdenCompra;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrdenCompraMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrdenCompra $orden,
        public string $cuerpo,
        public string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Orden de compra {$this->orden->numero} · Vialum",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orden-compra',
            with: [
                'orden'  => $this->orden,
                'cuerpo' => $this->cuerpo,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, "{$this->orden->numero}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
