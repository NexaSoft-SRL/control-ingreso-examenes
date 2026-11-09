<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Reportes\Application\Contracts\BitacoraExamenGateway;
use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\DTOs\BitacoraExamenData;
use App\Modules\Reportes\Domain\Exceptions\RecursoAjenoException;

/**
 * La bitacora de un examen (quien controlo cada aula y los sucesos en
 * orden): para un docente del examen o, con `$deLaUniversidad`, para quien
 * ve los reportes de toda la universidad.
 */
final readonly class ConsultarBitacoraExamen
{
    public function __construct(
        private BitacoraExamenGateway $bitacora,
        private ReporteGateway $reportes,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * Null si el examen no existe.
     *
     *
     * @throws RecursoAjenoException
     */
    public function execute(int $examenId, int $usuarioId, bool $deLaUniversidad = false): ?BitacoraExamenData
    {
        if ($this->reportes->examen($examenId) === null) {
            return null;
        }

        if (! $deLaUniversidad && ! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new RecursoAjenoException('Este examen no está a su cargo.');
        }

        return new BitacoraExamenData(
            aulas: $this->bitacora->controlDeAulas($examenId),
            sucesos: $this->bitacora->sucesos($examenId),
        );
    }
}
