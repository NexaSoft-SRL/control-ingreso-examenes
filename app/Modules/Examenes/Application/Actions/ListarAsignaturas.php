<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;
use App\Modules\Examenes\Domain\Models\Asignatura;

final readonly class ListarAsignaturas
{
    public function __construct(
        private AsignaturaGateway $gateway,
    ) {}

    /**
     * @return list<Asignatura>
     */
    public function execute(): array
    {
        return $this->gateway->listar();
    }
}
