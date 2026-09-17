<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;
use App\Modules\Examenes\Application\DTOs\RegistrarAsignaturaData;
use App\Modules\Examenes\Domain\Models\Asignatura;

final readonly class RegistrarAsignatura
{
    public function __construct(
        private AsignaturaGateway $gateway,
    ) {}

    public function execute(
        RegistrarAsignaturaData $data,
    ): Asignatura {
        return $this->gateway->registrar($data);
    }
}
