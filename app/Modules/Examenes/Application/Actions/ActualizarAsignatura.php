<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;
use App\Modules\Examenes\Application\DTOs\RegistrarAsignaturaData;
use App\Modules\Examenes\Domain\Models\Asignatura;

final readonly class ActualizarAsignatura
{
    public function __construct(
        private AsignaturaGateway $gateway,
    ) {}

    public function execute(
        int $asignaturaId,
        RegistrarAsignaturaData $data,
        int $usuarioId,
    ): ?Asignatura {
        return $this->gateway->actualizar(
            $asignaturaId,
            $data,
            $usuarioId,
        );
    }
}
