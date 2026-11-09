<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\Queries;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Monitoreo\Application\Contracts\SeguimientoGateway;
use App\Modules\Monitoreo\Application\DTOs\EnCursoData;
use App\Modules\Monitoreo\Domain\Exceptions\SinAlcanceException;

/**
 * «En curso» de un examen: sus cifras y la situacion de cada aula. Solo
 * para un docente del examen.
 */
final readonly class ConsultarEnCurso
{
    public function __construct(
        private SeguimientoGateway $seguimiento,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * Null si el examen no existe.
     *
     * @throws SinAlcanceException
     */
    public function execute(int $usuarioId, int $examenId): ?EnCursoData
    {
        $examen = $this->seguimiento->examen($examenId);

        if ($examen === null) {
            return null;
        }

        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new SinAlcanceException('Este examen no está a su cargo.');
        }

        return new EnCursoData(
            examen: $examen,
            cifras: $this->seguimiento->cifras($examenId),
            aulas: $this->seguimiento->aulas($examenId, $usuarioId),
        );
    }
}
