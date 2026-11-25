<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\InvitacionExamenGateway;
use App\Modules\Examenes\Application\DTOs\InvitacionRecibidaData;

/**
 * Las invitaciones pendientes de otros docentes a sumar un grupo suyo a un
 * examen que aun no pasa.
 */
final readonly class ListarInvitacionesDeExamen
{
    public function __construct(
        private InvitacionExamenGateway $invitaciones,
    ) {}

    /**
     * @return list<InvitacionRecibidaData>
     */
    public function execute(int $usuarioId): array
    {
        return $this->invitaciones->pendientesDe($usuarioId);
    }
}
