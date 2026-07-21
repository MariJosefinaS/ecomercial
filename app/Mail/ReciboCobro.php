<?php

namespace App\Mail;

use App\Models\Cobro;
use App\Support\Recibo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail del recibo de cobro al cliente. Cuerpo con el resumen + PDF adjunto.
 * Sirve de alerta post-cobro (el cliente se entera de lo impactado) y de comprobante.
 */
class ReciboCobro extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Cobro $cobro)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibo de tu pago — E.Comercial (' . Recibo::numero($this->cobro) . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recibo-cobro',
            with: Recibo::datos($this->cobro),
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => Recibo::pdf($this->cobro)->output(), Recibo::nombreArchivo($this->cobro))
                ->withMime('application/pdf'),
        ];
    }
}
