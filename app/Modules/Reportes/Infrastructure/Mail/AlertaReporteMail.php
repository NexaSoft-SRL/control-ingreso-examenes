<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AlertaReporteMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @param  array{id:int, email:string, asignatura:string, tipo_examen:string, fecha:string, titulo:string, mensaje:string}  $alerta
     */
    public function __construct(public readonly array $alerta) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alerta['titulo']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'correos.alerta-reporte',
            with: ['alerta' => $this->alerta],
        );
    }

    public function send($mailer)
    {
        try {
            $resultado = parent::send($mailer);
        } catch (Throwable $error) {
            $this->registrarError($error);

            throw $error;
        }

        DB::table('alertas_reportes')
            ->where('id', $this->alerta['id'])
            ->update([
                'correo_estado' => 'ENVIADO',
                'correo_error' => null,
                'updated_at' => now(),
            ]);

        return $resultado;
    }

    public function failed(Throwable $error): void
    {
        $this->registrarError($error);
    }

    private function registrarError(Throwable $error): void
    {
        DB::table('alertas_reportes')
            ->where('id', $this->alerta['id'])
            ->update([
                'correo_estado' => 'FALLIDO',
                'correo_error' => mb_substr($error->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);
    }
}
