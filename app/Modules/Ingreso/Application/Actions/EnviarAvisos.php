<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcanceCodigosQr;
use App\Modules\Ingreso\Application\Contracts\CorreoQrGateway;
use App\Modules\Ingreso\Application\Contracts\EmisionQrGateway;
use App\Modules\Ingreso\Application\DTOs\AvisoData;
use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;
use App\Modules\Ingreso\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Ingreso\Domain\Exceptions\SinCodigosException;

/**
 * Avisa por correo a los habilitados del filtro, tengan o no codigo: la
 * fecha, la hora y el aula que les toca, las normas del examen (las
 * marcadas y el texto libre) y las recomendaciones del docente.
 *
 * Con tope por peticion (cola sincronica) avanza por tandas: `tanda` es el
 * instante en que empezo y quien ya tiene un aviso desde entonces no lo
 * recibe otra vez.
 */
final readonly class EnviarAvisos
{
    public function __construct(
        private EmisionQrGateway $codigos,
        private CorreoQrGateway $correo,
        private AlcanceCodigosQr $alcance,
    ) {}

    /**
     * @return array{encolados: int, pendientes: int, tanda: string}
     *
     * @throws ExamenAjenoException
     * @throws SinCodigosException
     */
    public function execute(
        int $examenId,
        FiltroCodigosData $filtro,
        ?string $recomendaciones,
        int $usuarioId,
        ?string $tanda = null,
    ): array {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        // El aviso lleva el aula: no sale mientras haya habilitados sin ella.
        $sinAula = $this->codigos->resumen($examenId)['sin_aula'];

        if ($tanda === null && $sinAula > 0) {
            throw SinCodigosException::sinAula($sinAula);
        }

        $limite = $this->correo->tamanoDeLote();
        $continua = $tanda !== null;
        $tanda ??= $this->correo->ahora();

        $destinatarios = $this->codigos->paraAvisar(
            $examenId,
            $filtro,
            $limite,
            $continua || $limite !== null ? $tanda : null,
        );

        if ($destinatarios === []) {
            if ($continua) {
                return ['encolados' => 0, 'pendientes' => 0, 'tanda' => $tanda];
            }

            throw SinCodigosException::sinHabilitados();
        }

        $pendientes = $limite === null
            ? 0
            : max(0, $this->codigos->porAvisar($examenId, $filtro, $tanda) - count($destinatarios));

        $examen = $this->codigos->examen($examenId);
        $texto = trim((string) $recomendaciones);

        return [
            'encolados' => $this->correo->avisar(
                $examenId,
                $destinatarios,
                new AvisoData(
                    normasMarcadas: $examen === null ? [] : $examen->normasMarcadas,
                    normas: $examen?->normas,
                    recomendaciones: $texto === '' ? null : $texto,
                ),
                $usuarioId,
            ),
            'pendientes' => $pendientes,
            'tanda' => $tanda,
        ];
    }
}
