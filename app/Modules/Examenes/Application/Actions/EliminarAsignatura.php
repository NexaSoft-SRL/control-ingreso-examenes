<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;

final readonly class EliminarAsignatura
{
    public function __construct(
        private AsignaturaGateway $gateway,
    ) {}

    public function execute(
        int $asignaturaId,
        int $usuarioId,
    ): bool {
        return $this->gateway->eliminar(
            $asignaturaId,
            $usuarioId,
        );
    }
}
