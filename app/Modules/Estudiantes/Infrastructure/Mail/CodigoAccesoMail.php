<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class CodigoAccesoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $nombre,
        public readonly string $codigo,
        public readonly int $minutos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código de acceso: '.$this->codigo,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'correos.codigo_estudiante',
            with: [
                'nombre' => $this->nombre,
                'codigo' => $this->codigo,
                'minutos' => $this->minutos,
            ],
        );
    }
}
