<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenNoAbiertoException;

/**
 * Un docente del examen lo finaliza (HU-28): la duracion registrada es la
 * prevista, pero el examen puede pasarse, asi que la puerta, el seguimiento
 * y el acceso con codigo de aula se cierran recien aqui. Sin finalizar, el
 * examen se cierra solo al terminar su dia. No se deshace.
 */
final readonly class FinalizarExamen
{
    public function __construct(
        private ExamenGateway $examenes,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * Null si el examen no existe; si no, el instante de cierre
     * (`AAAA-MM-DD HH:MM:SS`).
     *
     * @throws ExamenAjenoException
     * @throws ExamenNoAbiertoException
     */
    public function execute(int $examenId, int $usuarioId): ?string
    {
        if (! $this->examenes->existe($examenId)) {
            return null;
        }

        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        return $this->examenes->finalizar($examenId, $usuarioId)
            ?? throw new ExamenNoAbiertoException;
    }
}
