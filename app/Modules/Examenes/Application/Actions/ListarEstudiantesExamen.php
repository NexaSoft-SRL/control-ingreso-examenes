<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\EstudianteExamenGateway;

final readonly class ListarEstudiantesExamen
{
    public function __construct(
        private EstudianteExamenGateway $gateway,
    ) {}

    /**
     * @return list<array{id: int|string, nombre: string, codigo_universitario: string|null}>
     */
    public function execute(): array
    {
        return $this->gateway->listarParaNormas();
    }
}
