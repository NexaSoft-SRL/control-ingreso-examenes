<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Habilitacion\Application\Contracts\RepartoGateway;
use App\Modules\Habilitacion\Application\DTOs\RepartoData;
use App\Modules\Habilitacion\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Habilitacion\Domain\Exceptions\ExamenSinAulasException;

/**
 * «Repartir»: los habilitados sin aula van al aula con menos asignados.
 */
final readonly class RepartirPorAula
{
    public function __construct(
        private RepartoGateway $reparto,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * @throws ExamenAjenoException
     * @throws ExamenSinAulasException
     */
    public function execute(int $examenId, int $usuarioId): RepartoData
    {
        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        if ($this->reparto->porAula($examenId) === []) {
            throw new ExamenSinAulasException;
        }

        return $this->reparto->repartir($examenId, $usuarioId);
    }
}
