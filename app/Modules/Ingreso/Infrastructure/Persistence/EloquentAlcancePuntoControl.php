<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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

        // 1. Personal asignado en examen_aula_personal (a_cargo o apoyo)
        $asignadoEnAtencion = DB::table('examen_aula_personal')
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->where('usuario_id', $usuarioId)
            ->exists();

        if ($asignadoEnAtencion) {
            return true;
        }

        // 2. Docente creador del examen o docente de un grupo incluido
        $esDocenteDelExamen = DB::table('examenes as e')
            ->join('examen_aula as ea', 'ea.examen_id', '=', 'e.id')
            ->where('e.id', $examenId)
            ->where('ea.aula_id', $aulaId)
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

        if ($esDocenteDelExamen) {
            return true;
        }

        // 3. Auxiliar asignado en asignaciones_seguimiento_aula (HU-21)
        return DB::table('asignaciones_seguimiento_aula')
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->where('usuario_id', $usuarioId)
            ->exists();
    }
}
