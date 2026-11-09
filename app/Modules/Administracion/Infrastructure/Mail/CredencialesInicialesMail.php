<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class CredencialesInicialesMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $nombre,
        public readonly string $usuario,
        public readonly string $contrasenaTemporal,
        public readonly string $enlace,
        public readonly int $horasVigencia,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sus credenciales de acceso',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'correos.credenciales',
            with: [
                'nombre' => $this->nombre,
                'usuario' => $this->usuario,
                'contrasenaTemporal' => $this->contrasenaTemporal,
                'enlace' => $this->enlace,
                'horasVigencia' => $this->horasVigencia,
            ],
        );
    }
}
