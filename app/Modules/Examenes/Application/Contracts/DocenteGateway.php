<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\RegistrarDocenteData;
use App\Modules\Examenes\Domain\Models\Docente;

interface DocenteGateway
{
    /**
     * @return list<Docente>
     */
    public function listarActivos(): array;

    public function registrar(
        RegistrarDocenteData $data,
        int $usuarioId,
    ): Docente;
}
