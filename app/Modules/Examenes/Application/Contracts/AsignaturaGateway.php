<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\RegistrarAsignaturaData;
use App\Modules\Examenes\Domain\Models\Asignatura;

interface AsignaturaGateway
{
    public function registrar(
        RegistrarAsignaturaData $data,
        int $usuarioId,
    ): Asignatura;

    /**
     * @return list<Asignatura>
     */
    public function listar(): array;

    public function eliminar(
        int $asignaturaId,
        int $usuarioId,
    ): bool;
}
