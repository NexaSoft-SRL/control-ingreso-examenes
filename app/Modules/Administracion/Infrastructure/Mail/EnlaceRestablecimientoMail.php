<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class EnlaceRestablecimientoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $nombre,
        public readonly string $enlace,
        public readonly int $minutosVigencia,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Restablecer contraseña');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'correos.restablecimiento',
            with: [
                'nombre' => $this->nombre,
                'enlace' => $this->enlace,
                'minutosVigencia' => $this->minutosVigencia,
            ],
        );
    }
}
