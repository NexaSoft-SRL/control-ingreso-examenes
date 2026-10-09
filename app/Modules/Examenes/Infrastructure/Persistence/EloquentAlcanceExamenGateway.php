<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Domain\Models\Examen;
use Illuminate\Support\Facades\DB;

/**
 * Los grupos y los docentes son de otro modulo: se leen por nombre de
 * tabla, sin importar sus modelos.
 */
final class EloquentAlcanceExamenGateway implements AlcanceExamenGateway
{
    public function esDocenteDelExamen(int $usuarioId, int $examenId): bool
    {
        if ($this->loRegistro($usuarioId, $examenId)) {
            return true;
        }

        return DB::table('examen_grupo as incluido')
            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
            ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
            ->where('incluido.examen_id', $examenId)
            ->where('docente.user_id', $usuarioId)
            ->exists();
    }

    public function loRegistro(int $usuarioId, int $examenId): bool
    {
        return Examen::where('id', $examenId)
            ->where('creado_por', $usuarioId)
            ->exists();
    }
}
