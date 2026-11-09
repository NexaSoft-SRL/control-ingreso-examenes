<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\CodigoAulaGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\CodigoAulaData;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;

/**
 * El codigo de acceso vigente de un aula del examen. Consultarlo no escribe:
 * si el aula todavia no tiene, `codigo` va nulo y lo genera
 * `RegenerarCodigoDeAula`.
 */
final readonly class ConsultarCodigoDeAula
{
    public function __construct(
        private ExamenGateway $examenes,
        private AlcanceExamenGateway $alcance,
        private CodigoAulaGateway $codigos,
    ) {}

    /**
     * Nulo si el examen no existe o el aula no es suya.
     *
     * @throws ExamenAjenoException
     */
    public function execute(int $examenId, int $aulaId, int $usuarioId): ?CodigoAulaData
    {
        if (! $this->examenes->existe($examenId)) {
            return null;
        }

        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        return $this->codigos->consultar($examenId, $aulaId);
    }
}
