<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Authorization;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Ingreso\Domain\Exceptions\ExamenAjenoException;

/**
 * Los codigos QR de un examen los opera su docente: quien lo registro o
 * el docente de alguno de sus grupos, el mismo criterio de la habilitacion.
 */
final readonly class AlcanceCodigosQr
{
    public function __construct(
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * @throws ExamenAjenoException
     */
    public function exigirDocente(int $usuarioId, int $examenId): void
    {
        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }
    }
}
