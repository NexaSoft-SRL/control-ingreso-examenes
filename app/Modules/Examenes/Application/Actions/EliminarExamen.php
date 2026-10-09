<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenConIngresosException;

/**
 * Solo quien registro el examen lo elimina, y solo mientras no tenga
 * ingresos registrados.
 */
final readonly class EliminarExamen
{
    public function __construct(
        private ExamenGateway $examenes,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * @throws ExamenAjenoException
     * @throws ExamenConIngresosException
     */
    public function execute(int $examenId, int $usuarioId): bool
    {
        if (! $this->examenes->existe($examenId)) {
            return false;
        }

        if (! $this->alcance->loRegistro($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        if ($this->examenes->tieneIngresos($examenId)) {
            throw new ExamenConIngresosException;
        }

        return $this->examenes->eliminar($examenId, $usuarioId);
    }
}
