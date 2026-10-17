<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
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

        // 3. Auxiliar asignado en asignaciones_seguimiento_aula (HU-21)
        return DB::table('asignaciones_seguimiento_aula')
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->where('usuario_id', $usuarioId)
            ->exists();
    }
}
