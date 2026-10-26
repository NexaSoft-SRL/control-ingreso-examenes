<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\RegistroCorreoGateway;
use App\Modules\Administracion\Application\DTOs\RegistroCorreoData;
use App\Modules\Ingreso\Application\Contracts\CorreoQrGateway;
use App\Modules\Ingreso\Application\DTOs\AvisoData;
use App\Modules\Ingreso\Application\DTOs\CredencialData;
use App\Modules\Ingreso\Infrastructure\Mail\AvisoExamenMail;
use App\Modules\Ingreso\Infrastructure\Mail\CodigoQrMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Cada correo queda registrado en `envios_correo` (Administracion) antes
 * de salir; el propio correo cierra su registro al enviarse o al fallar.
 */
final class EloquentCorreoQrGateway implements CorreoQrGateway
{
    /**
     * Con la cola sincronica cada correo sale dentro de la peticion: se
     * envian por tandas para no agotar el tiempo del servidor.
     */
    private const LOTE_SINCRONICO = 100;

    public function __construct(
        private readonly BitacoraGateway $bitacora,
        private readonly RegistroCorreoGateway $registro,
    ) {}

    public function tamanoDeLote(): ?int
    {
        return config('queue.default') === 'sync' ? self::LOTE_SINCRONICO : null;
    }

    public function ahora(): string
    {
        return now()->toIso8601String();
    }

    public function encolar(int $examenId, array $credenciales, int $usuarioId): int
    {
        return $this->despachar(
            $examenId,
            $credenciales,
            $usuarioId,
            RegistroCorreoData::TIPO_CODIGO_QR,
            'qr.enviar',
            '%d código(s) QR enviado(s) por correo para el examen %d.',
            static fn (CredencialData $credencial, int $envioId): Mailable => new CodigoQrMail($credencial, $envioId),
        );
    }

    public function avisar(int $examenId, array $destinatarios, AvisoData $aviso, int $usuarioId): int
    {
        return $this->despachar(
            $examenId,
            $destinatarios,
            $usuarioId,
            RegistroCorreoData::TIPO_AVISO,
            'aviso.enviar',
            '%d aviso(s) del examen %d enviado(s) por correo.',
            static fn (CredencialData $credencial, int $envioId): Mailable => new AvisoExamenMail($credencial, $aviso, $envioId),
        );
    }

    /**
     * @param  list<CredencialData>  $credenciales
     * @param  callable(CredencialData, int): Mailable  $correoDe
     */
    private function despachar(
        int $examenId,
        array $credenciales,
        int $usuarioId,
        string $tipo,
        string $operacion,
        string $descripcion,
        callable $correoDe,
    ): int {
        /** @var list<array{envio: int, credencial: CredencialData}> $envios */
        $envios = DB::transaction(function () use ($examenId, $credenciales, $usuarioId, $tipo, $operacion, $descripcion): array {
            $envios = [];

            foreach ($credenciales as $credencial) {
                $envios[] = [
                    'envio' => $this->registro->registrar(new RegistroCorreoData(
                        tipo: $tipo,
                        destinatario: $credencial->correo,
                        examenId: $examenId,
                        estudianteId: $credencial->estudianteId,
                        solicitadoPor: $usuarioId,
                    )),
                    'credencial' => $credencial,
                ];
            }

            $this->bitacora->registrar(
                $usuarioId,
                $operacion,
                'examenes',
                $examenId,
                sprintf($descripcion, count($envios), $examenId),
            );

            return $envios;
        }, 3);

        // Fuera de la transaccion: el registro queda aunque un correo
        // falle. Con la cola sincronica el envio ocurre aqui mismo y su
        // fallo no debe tumbar la peticion.
        foreach ($envios as $envio) {
            try {
                Mail::to($envio['credencial']->correo)
                    ->queue($correoDe($envio['credencial'], $envio['envio']));
            } catch (Throwable $error) {
                $this->registro->marcarFallido($envio['envio'], $error->getMessage());
            }
        }

        return count($envios);
    }
}
