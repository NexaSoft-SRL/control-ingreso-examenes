<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Mail;

use App\Modules\Administracion\Application\Contracts\RegistroCorreoGateway;
use App\Modules\Ingreso\Application\DTOs\AvisoData;
use App\Modules\Ingreso\Application\DTOs\CredencialData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * El aviso de un examen para un estudiante: fecha, hora, aula, normas y
 * las recomendaciones del docente. Va en cola; al salir marca su envio y,
 * si falla, deja el error. No toca el codigo QR.
 */
final class AvisoExamenMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public CredencialData $credencial,
        public AvisoData $aviso,
        public int $envioId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aviso de examen · '.$this->credencial->examen,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'correos.aviso_examen',
            with: [
                'credencial' => $this->credencial,
                'aviso' => $this->aviso,
            ],
        );
    }

    public function send($mailer)
    {
        $enviado = parent::send($mailer);

        app(RegistroCorreoGateway::class)->marcarEnviado($this->envioId);

        return $enviado;
    }

    public function failed(Throwable $error): void
    {
        app(RegistroCorreoGateway::class)->marcarFallido($this->envioId, $error->getMessage());
    }
}
