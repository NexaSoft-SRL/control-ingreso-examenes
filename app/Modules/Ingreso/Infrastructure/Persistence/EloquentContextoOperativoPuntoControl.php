<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\ContextoOperativoPuntoControl;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EloquentContextoOperativoPuntoControl extends ContextoOperativoSinAsignaciones implements ContextoOperativoPuntoControl
{
    public function examenesAutorizados(int $usuarioId): array
    {
        $hoy = now()->toDateString();

        // 1. Aulas autorizadas por atencion (HU-18)
        $atencionFilas = DB::table('examen_aula_personal as p')
            ->join('examenes as e', 'e.id', '=', 'p.examen_id')
            ->join('aulas as a', 'a.id', '=', 'p.aula_id')
            ->join('asignaturas as asig', 'asig.id', '=', 'e.asignatura_id')
            ->where('p.usuario_id', $usuarioId)
            ->whereDate('e.fecha', $hoy)
            ->select([
                'e.id as examen_id',
                'e.fecha',
                'e.tipo',
                'asig.nombre as asignatura_nombre',
                'a.id as aula_id',
                'a.nombre as aula_nombre',
            ])
            ->get();

        // 2. Aulas autorizadas por seguimiento (HU-21)
        $seguimientoFilas = DB::table('asignaciones_seguimiento_aula as s')
            ->join('examenes as e', 'e.id', '=', 's.examen_id')
            ->join('aulas as a', 'a.id', '=', 's.aula_id')
            ->join('asignaturas as asig', 'asig.id', '=', 'e.asignatura_id')
            ->where('s.usuario_id', $usuarioId)
            ->whereDate('e.fecha', $hoy)
            ->select([
                'e.id as examen_id',
                'e.fecha',
                'e.tipo',
                'asig.nombre as asignatura_nombre',
                'a.id as aula_id',
                'a.nombre as aula_nombre',
            ])
            ->get();

        // 3. Si es docente creador o de grupo del examen
        $docenteFilas = DB::table('examenes as e')
            ->join('asignaturas as asig', 'asig.id', '=', 'e.asignatura_id')
            ->join('examen_aula as ea', 'ea.examen_id', '=', 'e.id')
            ->join('aulas as a', 'a.id', '=', 'ea.aula_id')
            ->whereDate('e.fecha', $hoy)
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
            ->select([
                'e.id as examen_id',
                'e.fecha',
                'e.tipo',
                'asig.nombre as asignatura_nombre',
                'a.id as aula_id',
                'a.nombre as aula_nombre',
            ])
            ->get();

        $todas = $atencionFilas->concat($seguimientoFilas)->concat($docenteFilas);

        if ($todas->isEmpty()) {
            return [];
        }

        $examenes = [];
        foreach ($todas as $fila) {
            $exId = property_exists($fila, 'examen_id') && is_numeric($fila->examen_id) ? (int) $fila->examen_id : 0;
            if ($exId === 0) {
                continue;
            }

            if (! isset($examenes[$exId])) {
                $fechaStr = property_exists($fila, 'fecha') && is_string($fila->fecha) ? $fila->fecha : '';
                $tipoStr = property_exists($fila, 'tipo') && is_string($fila->tipo) ? $fila->tipo : '';
                $asigStr = property_exists($fila, 'asignatura_nombre') && is_string($fila->asignatura_nombre) ? $fila->asignatura_nombre : '';

                $examenes[$exId] = [
                    'id' => $exId,
                    'fecha' => substr($fechaStr, 0, 10),
                    'tipo' => $tipoStr,
                    'asignatura' => [
                        'nombre' => $asigStr,
                    ],
                    'aulas' => [],
                ];
            }

            $aulaId = property_exists($fila, 'aula_id') && is_numeric($fila->aula_id) ? (int) $fila->aula_id : 0;
            if ($aulaId === 0) {
                continue;
            }

            $yaEsta = false;
            foreach ($examenes[$exId]['aulas'] as $au) {
                if ($au['id'] === $aulaId) {
                    $yaEsta = true;
                    break;
                }
            }

            if (! $yaEsta) {
                $aulaNombreStr = property_exists($fila, 'aula_nombre') && is_string($fila->aula_nombre) ? $fila->aula_nombre : '';
                $examenes[$exId]['aulas'][] = [
                    'id' => $aulaId,
                    'nombre' => $aulaNombreStr,
                ];
            }
        }

        return array_values($examenes);
    }
}
