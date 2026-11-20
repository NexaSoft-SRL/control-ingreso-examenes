<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenNoAbiertoException;

/**
 * Un docente suspende temporalmente el examen. Mientras exista una
 * suspensión abierta, la puerta deja de admitir ingresos y el examen
 * permanece detenido hasta su reanudación.
 */
final readonly class SuspenderExamen
{
    public function __construct(
        private ExamenGateway $examenes,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * Null si el examen no existe; si no, el instante de suspensión
     * (`AAAA-MM-DD HH:MM:SS`).
     *
     * @throws ExamenAjenoException
     * @throws ExamenNoAbiertoException
     */
    public function execute(
        int $examenId,
        int $usuarioId,
        string $motivo,
        ?string $descripcion,
    ): ?string {
        if (! $this->examenes->existe($examenId)) {
            return null;
        }

        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        return $this->examenes->suspender(
            $examenId,
            $usuarioId,
            $motivo,
            $descripcion,
        ) ?? throw new ExamenNoAbiertoException;
    }
}
