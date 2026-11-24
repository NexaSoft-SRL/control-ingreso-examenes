<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenNoAbiertoException;

/**
 * Reanuda un examen previamente suspendido.
 */
final readonly class ReanudarExamen
{
    public function __construct(
        private ExamenGateway $examenes,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * Null si el examen no existe; si no, el instante de reanudación
     * (`AAAA-MM-DD HH:MM:SS`).
     *
     * @throws ExamenAjenoException
     * @throws ExamenNoAbiertoException
     */
    public function execute(
        int $examenId,
        int $usuarioId,
    ): ?string {
        if (! $this->examenes->existe($examenId)) {
            return null;
        }

        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        return $this->examenes->reanudar(
            $examenId,
            $usuarioId,
        ) ?? throw new ExamenNoAbiertoException;
    }
}
