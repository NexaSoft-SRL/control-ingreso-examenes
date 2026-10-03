<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Domain\Models\Examen;

final class EloquentExamenGateway implements ExamenGateway
{
    /**
     * @return list<Examen>
     */
    public function listar(): array
    {
        $examenes = Examen::query()
            ->with(['grupo.asignatura', 'grupo.docente'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->all();

        return array_values($examenes);
    }

    public function buscar(int $examenId): ?Examen
    {
        return Examen::query()
            ->with(['grupo.asignatura', 'grupo.docente'])
            ->whereKey($examenId)
            ->first();
    }
}
