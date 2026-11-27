<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Mail;

use App\Modules\Ingreso\Application\DTOs\CredencialData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RecordatorioExamenMail extends Mailable implements ShouldQueue
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
            subject: 'Recordatorio: examen mañana · '.$this->credencial->examen,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'correos.recordatorio_examen',
            with: ['credencial' => $this->credencial],
        );
    }

    public function send($mailer)
    {
        $enviado = parent::send($mailer);
        $ahora = now();

        DB::table('envios_correo')
            ->where('id', $this->envioId)
            ->update([
                'estado' => 'ENVIADO',
                'error' => null,
                'enviado_en' => $ahora,
                'updated_at' => $ahora,
            ]);

        return $enviado;
    }

    public function failed(Throwable $error): void
    {
        CodigoQrMail::marcarFallido($this->envioId, $error->getMessage());
    }
}
