<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Mail;

use App\Modules\Administracion\Application\Contracts\RegistroCorreoGateway;
use App\Modules\Ingreso\Application\Contracts\GeneradorImagenQr;
use App\Modules\Ingreso\Application\DTOs\CredencialData;
use App\Modules\Ingreso\Domain\Models\CodigoQr;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * El codigo QR de un estudiante para un examen, con donde rinde. Va en
 * cola; al salir marca su envio y el codigo como recibidos y, si falla,
 * deja el error en el envio.
 */
final class CodigoQrMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public CredencialData $credencial,
        public int $envioId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Su código QR · '.$this->credencial->examen,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'correos.codigo_qr',
            with: [
                'credencial' => $this->credencial,
                'png' => $this->png(),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->png(), 'codigo-qr.png')
                ->withMime('image/png'),
        ];
    }

    public function send($mailer)
    {
        $enviado = parent::send($mailer);

        app(RegistroCorreoGateway::class)->marcarEnviado($this->envioId);

        CodigoQr::query()
            ->where('examen_id', $this->credencial->examenId)
            ->where('estudiante_id', $this->credencial->estudianteId)
            ->update(['enviado_en' => now()]);

        return $enviado;
    }

    public function failed(Throwable $error): void
    {
        app(RegistroCorreoGateway::class)->marcarFallido($this->envioId, $error->getMessage());
    }

    private function png(): string
    {
        return app(GeneradorImagenQr::class)->comoPng($this->credencial->contenido ?? '');
    }
}
