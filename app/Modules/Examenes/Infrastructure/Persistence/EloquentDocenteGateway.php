<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\DocenteGateway;
use App\Modules\Examenes\Domain\Models\Docente;

final class EloquentDocenteGateway implements DocenteGateway
{
    /**
     * @return list<Docente>
     */
    public function listarActivos(): array
    {
        $docentes = Docente::query()
            ->where('estado', true)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get()
            ->all();

        return array_values($docentes);
    }
}
