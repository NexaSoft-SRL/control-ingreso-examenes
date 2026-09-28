<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\DocenteGateway;
use App\Modules\Examenes\Application\DTOs\RegistrarDocenteData;
use App\Modules\Examenes\Domain\Models\Docente;

final readonly class RegistrarDocente
{
    public function __construct(
        private DocenteGateway $gateway,
    ) {}

    public function execute(
        RegistrarDocenteData $data,
        int $usuarioId,
    ): Docente {
        return $this->gateway->registrar(
            $data,
            $usuarioId,
        );
    }
}
