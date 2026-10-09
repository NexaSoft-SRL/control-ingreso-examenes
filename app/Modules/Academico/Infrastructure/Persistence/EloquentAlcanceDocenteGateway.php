<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;

final class EloquentAlcanceDocenteGateway implements AlcanceDocenteGateway
{
    public function docenteDeUsuario(int $usuarioId): ?int
    {
        $docente = Docente::where('user_id', $usuarioId)->first();

        return $docente instanceof Docente ? $docente->id : null;
    }

    public function esGrupoDelDocente(int $usuarioId, int $grupoId): bool
    {
        $docenteId = $this->docenteDeUsuario($usuarioId);

        if ($docenteId === null) {
            return false;
        }

        return Grupo::where('id', $grupoId)
            ->where('docente_id', $docenteId)
            ->exists();
    }
}
