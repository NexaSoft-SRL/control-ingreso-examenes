<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Domain\Models\Docente;

interface DocenteGateway
{
    /**
     * @return list<Docente>
     */
    public function listarActivos(): array;
}
