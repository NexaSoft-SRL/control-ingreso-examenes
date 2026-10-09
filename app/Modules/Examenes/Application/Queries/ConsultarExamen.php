<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\DTOs\ExamenDetalleData;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;

/**
 * El examen completo, para quien lo registro y para los docentes de sus
 * grupos.
 */
final readonly class ConsultarExamen
{
    public function __construct(
        private ConsultaExamenGateway $consulta,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * @throws ExamenAjenoException
     */
    public function execute(int $examenId, int $usuarioId): ?ExamenDetalleData
    {
        $examen = $this->consulta->detalle($examenId, $usuarioId);

        if ($examen === null) {
            return null;
        }

        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        return $examen;
    }
}
