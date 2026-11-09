<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
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
 * `envios_correo` es de Administracion: se escribe por nombre de tabla,
 * con los valores de sus enumeraciones (`CODIGO_QR`, `PENDIENTE`). El
 * aviso usa un tipo propio, `AVISO`.
 */
final class EloquentCorreoQrGateway implements CorreoQrGateway
{
    use LeeFilas;

    private const LOTE_SINCRONICO = 100;

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function tamanoDeLote(): ?int
    {
        return config('queue.default') === 'sync' ? self::LOTE_SINCRONICO : null;
    }

    public function encolar(int $examenId, array $credenciales, ?int $usuarioId): int
    {
        return $this->despachar(
            $examenId,
            $credenciales,
            $usuarioId,
            'CODIGO_QR',
            'qr.enviar',
            'codigos_qr',
            '%d código(s) QR enviado(s) por correo para el examen %d.',
            static fn (CredencialData $credencial, int $envioId): Mailable => new CodigoQrMail($credencial, $envioId),
        );
    }

    public function avisar(int $examenId, array $destinatarios, AvisoData $aviso, ?int $usuarioId): int
    {
        return $this->despachar(
            $examenId,
            $destinatarios,
            $usuarioId,
            'AVISO',
            'aviso.enviar',
            'examenes',
            '%d aviso(s) enviado(s) por correo para el examen %d.',
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
        ?int $usuarioId,
        string $tipo,
        string $operacion,
        string $tabla,
        string $descripcion,
        callable $correoDe,
    ): int {
        /** @var list<array{envio: int, destinatario: string, credencial: CredencialData}> $envios */
        $envios = DB::transaction(function () use ($examenId, $credenciales, $usuarioId, $tipo, $operacion, $tabla, $descripcion): array {
            $ahora = now();
            $envios = [];

            foreach ($credenciales as $credencial) {
                $destinatario = $credencial->correo ?? $this->correoInstitucional($credencial->codigo);

                $envios[] = [
                    'envio' => DB::table('envios_correo')->insertGetId([
                        'tipo' => $tipo,
                        'destinatario' => $destinatario,
                        'examen_id' => $examenId,
                        'estudiante_id' => $credencial->estudianteId,
                        'estado' => 'PENDIENTE',
                        'solicitado_por' => $usuarioId,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]),
                    'destinatario' => $destinatario,
                    'credencial' => $credencial,
                ];
            }

            $this->bitacora->registrar(
                $usuarioId,
                $operacion,
                $tabla,
                $examenId,
                sprintf($descripcion, count($envios), $examenId),
            );

            return $envios;
        }, 3);

        // Fuera de la transaccion: las filas de envio quedan aunque un
        // correo falle. Con la cola sincronica el envio ocurre aqui mismo
        // y su fallo no debe tumbar la peticion; el propio correo ya dejo
        // su fila como fallida.
        foreach ($envios as $envio) {
            try {
                Mail::to($envio['destinatario'])
                    ->queue($correoDe($envio['credencial'], $envio['envio']));
            } catch (Throwable $error) {
                CodigoQrMail::marcarFallido($envio['envio'], $error->getMessage());
            }
        }

        return count($envios);
    }
}
