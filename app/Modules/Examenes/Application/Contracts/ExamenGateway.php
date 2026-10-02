<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Domain\Models\Examen;

interface ExamenGateway
{
    /**
     * @return list<Examen>
     */
    public function listar(): array;
}
