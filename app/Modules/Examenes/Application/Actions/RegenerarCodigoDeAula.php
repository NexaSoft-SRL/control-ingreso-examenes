<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\CodigoAulaGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\CodigoAulaData;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;

/**
 * Genera el codigo de acceso de un aula o, si ya tenia, lo cambia: el
 * anterior deja de servir y los accesos abiertos con el se cierran.
 */
final readonly class RegenerarCodigoDeAula
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

        return $this->codigos->generar($examenId, $aulaId, $usuarioId, true);
    }
}
