<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Fuente unica: el personal por aula del examen (`examen_aula_personal`,
 * HU-18) y los docentes del examen. La tabla vieja
 * `asignaciones_seguimiento_aula` ya no concede la puerta: nadie la escribe.
 */
class EloquentAlcancePuntoControl extends AlcancePuntoControlSinAsignaciones implements AlcancePuntoControl
{
    public function puedeOperar(
        int $usuarioId,
        int $examenId,
        int $aulaId,
    ): bool {
        if ($usuarioId <= 0 || $examenId <= 0 || $aulaId <= 0) {
            return false;
        }

        return in_array($aulaId, $this->aulasOperables($usuarioId, $examenId), true);
    }

    public function aulasOperables(
        int $usuarioId,
        int $examenId,
    ): array {
        if ($usuarioId <= 0 || $examenId <= 0) {
            return [];
        }

        $aulasDelExamen = DB::table('examen_aula')
            ->where('examen_id', $examenId);

        // 1. Docente creador del examen o docente de un grupo incluido:
        //    todas las aulas del examen.
        $esDocenteDelExamen = DB::table('examenes as e')
            ->where('e.id', $examenId)
            ->where(function (Builder $query) use ($usuarioId): void {
                $query->where('e.creado_por', $usuarioId)
                    ->orWhereExists(function (Builder $sub) use ($usuarioId): void {
                        $sub->selectRaw('1')
                            ->from('examen_grupo as eg')
                            ->join('grupos as g', 'g.id', '=', 'eg.grupo_id')
                            ->join('docentes as d', 'd.id', '=', 'g.docente_id')
                            ->whereColumn('eg.examen_id', 'e.id')
                            ->where('d.user_id', $usuarioId);
                    });
            })
            ->exists();

        $ids = $esDocenteDelExamen
            ? $aulasDelExamen->pluck('aula_id')
            // 2. Personal asignado al aula (a cargo o apoyo).
            : DB::table('examen_aula_personal as p')
                ->join('examen_aula as ea', static function (JoinClause $join): void {
                    $join->on('ea.examen_id', '=', 'p.examen_id')
                        ->on('ea.aula_id', '=', 'p.aula_id');
                })
                ->where('p.examen_id', $examenId)
                ->where('p.usuario_id', $usuarioId)
                ->pluck('p.aula_id');

        $aulas = [];

        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $aulas[] = (int) $id;
            }
        }

        $aulas = array_values(array_unique($aulas));
        sort($aulas);

        return $aulas;
    }
}
