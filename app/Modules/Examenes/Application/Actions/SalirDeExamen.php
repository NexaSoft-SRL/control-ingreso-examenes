<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\Contracts\InvitacionExamenGateway;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenConIngresosException;

/**
 * Un docente que se sumo al examen de otro saca sus grupos de el. Quien
 * registro el examen no sale (lo elimina), y con ingresos ya no se sale.
 */
final readonly class SalirDeExamen
{
    public function __construct(
        private InvitacionExamenGateway $invitaciones,
        private ExamenGateway $examenes,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * @return bool false si el examen no existe
     *
     * @throws ExamenAjenoException
     * @throws ExamenConIngresosException
     */
    public function execute(int $examenId, int $usuarioId): bool
    {
        if (! $this->examenes->existe($examenId)) {
            return false;
        }

        $grupos = $this->invitaciones->gruposDelDocenteEnExamen($examenId, $usuarioId);

        if ($grupos === [] || $this->alcance->loRegistro($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        if ($this->examenes->tieneIngresos($examenId)) {
            throw new ExamenConIngresosException;
        }

        $this->invitaciones->salir($examenId, $grupos, $usuarioId);

        return true;
    }
}
