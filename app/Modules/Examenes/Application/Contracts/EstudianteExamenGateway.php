<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

interface EstudianteExamenGateway
{
    /**
     * @return list<array{id: int|string, nombre: string, codigo_universitario: string|null}>
     */
    public function listarParaNormas(): array;
}
