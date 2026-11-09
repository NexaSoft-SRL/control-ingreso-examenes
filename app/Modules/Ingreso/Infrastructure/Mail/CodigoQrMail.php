<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Mail;

use App\Modules\Ingreso\Application\Contracts\GeneradorImagenQr;
use App\Modules\Ingreso\Application\DTOs\CredencialData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * El codigo QR de un estudiante para un examen, con donde rinde. El QR va
 * dentro del correo y ademas adjunto como PNG, para guardarlo o compartirlo.
 * Va en cola; al salir marca su fila de `envios_correo` y el codigo como
 * enviados, y si falla deja el error.
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
            subject: 'Código QR · '.$this->credencial->examen,
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
            Attachment::fromData(fn (): string => $this->png(), $this->credencial->nombreDelQr())
                ->withMime('image/png'),
        ];
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

        DB::table('codigos_qr')
            ->where('examen_id', $this->credencial->examenId)
            ->where('estudiante_id', $this->credencial->estudianteId)
            ->update(['enviado_en' => $ahora, 'updated_at' => $ahora]);

        return $enviado;
    }

    public function failed(Throwable $error): void
    {
        self::marcarFallido($this->envioId, $error->getMessage());
    }

    public static function marcarFallido(int $envioId, string $error): void
    {
        DB::table('envios_correo')
            ->where('id', $envioId)
            ->where('estado', '!=', 'ENVIADO')
            ->update([
                'estado' => 'FALLIDO',
                'error' => mb_substr($error, 0, 2000),
                'updated_at' => now(),
            ]);
    }

    private function png(): string
    {
        return app(GeneradorImagenQr::class)->comoPng($this->credencial->token ?? '');
    }
}
