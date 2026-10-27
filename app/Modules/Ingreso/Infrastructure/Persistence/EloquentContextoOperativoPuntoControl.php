<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\EtiquetaTipoExamen;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\ContextoOperativoPuntoControl;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Los examenes de hoy y las aulas cuya puerta la persona puede operar, con
 * la misma regla que la puerta (`AlcancePuntoControl`). Cada examen dice si
 * su puerta esta abierta ahora; cada aula, su edificio y piso.
 */
class EloquentContextoOperativoPuntoControl extends ContextoOperativoSinAsignaciones implements ContextoOperativoPuntoControl
{
    public function __construct(
        private readonly AlcancePuntoControl $alcance,
        private readonly VentanaPuntoControl $ventana,
        private readonly EtiquetaTipoExamen $etiquetaTipo,
    ) {}

    public function examenesAutorizados(int $usuarioId): array
    {
        $hoy = now()->toDateString();

        // Examenes de hoy en los que la persona es docente o personal de aula.
        $candidatos = DB::table('examenes as e')
            ->join('asignaturas as asig', 'asig.id', '=', 'e.asignatura_id')
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
                    })
                    ->orWhereExists(function (Builder $sub) use ($usuarioId): void {
                        $sub->selectRaw('1')
                            ->from('examen_aula_personal as p')
                            ->whereColumn('p.examen_id', 'e.id')
                            ->where('p.usuario_id', $usuarioId);
                    });
            })
            ->orderBy('e.hora_inicio')
            ->orderBy('e.id')
            ->get([
                'e.id',
                'e.fecha',
                'e.tipo',
                'e.hora_inicio',
                'asig.nombre as asignatura_nombre',
            ]);

        $examenes = [];

        foreach ($candidatos as $fila) {
            $examenId = is_numeric($fila->id) ? (int) $fila->id : 0;

            if ($examenId === 0) {
                continue;
            }

            $aulaIds = $this->alcance->aulasOperables($usuarioId, $examenId);

            if ($aulaIds === []) {
                continue;
            }

            $tipo = is_string($fila->tipo) ? $fila->tipo : '';
            $hora = is_string($fila->hora_inicio) ? substr($fila->hora_inicio, 0, 5) : '';

            $examenes[] = [
                'id' => $examenId,
                'fecha' => substr(is_string($fila->fecha) ? $fila->fecha : '', 0, 10),
                'tipo' => $tipo,
                'tipo_etiqueta' => $this->etiquetaTipo->de($tipo),
                'hora' => $hora,
                'abierto' => $this->ventana->estaAbierta($examenId),
                'asignatura' => [
                    'nombre' => is_string($fila->asignatura_nombre) ? $fila->asignatura_nombre : '',
                ],
                'aulas' => $this->aulas($aulaIds),
            ];
        }

        return $examenes;
    }

    public function examenesDelAula(int $aulaId): array
    {
        $filas = DB::table('examenes as e')
            ->join('asignaturas as asig', 'asig.id', '=', 'e.asignatura_id')
            ->join('examen_aula as ea', 'ea.examen_id', '=', 'e.id')
            ->where('ea.aula_id', $aulaId)
            ->whereDate('e.fecha', now()->toDateString())
            ->orderBy('e.hora_inicio')
            ->orderBy('e.id')
            ->get(['e.id', 'e.fecha', 'e.tipo', 'e.hora_inicio', 'asig.nombre as asignatura_nombre']);

        $examenes = [];

        foreach ($filas as $fila) {
            $examenId = is_numeric($fila->id) ? (int) $fila->id : 0;
            $tipo = is_string($fila->tipo) ? $fila->tipo : '';

            $examenes[] = [
                'id' => $examenId,
                'fecha' => substr(is_string($fila->fecha) ? $fila->fecha : '', 0, 10),
                'tipo' => $tipo,
                'tipo_etiqueta' => $this->etiquetaTipo->de($tipo),
                'hora' => is_string($fila->hora_inicio) ? substr($fila->hora_inicio, 0, 5) : '',
                'abierto' => $this->ventana->estaAbierta($examenId),
                'asignatura' => [
                    'nombre' => is_string($fila->asignatura_nombre) ? $fila->asignatura_nombre : '',
                ],
                'aulas' => $this->aulas([$aulaId]),
            ];
        }

        return $examenes;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, nombre: string, edificio: string|null, piso: string|null}>
     */
    private function aulas(array $ids): array
    {
        $filas = DB::table('aulas as a')
            ->leftJoin('edificios as ed', 'ed.id', '=', 'a.edificio_id')
            ->whereIn('a.id', $ids)
            ->orderBy('a.nombre')
            ->get(['a.id', 'a.nombre', 'a.piso', 'ed.nombre as edificio']);

        $aulas = [];

        foreach ($filas as $fila) {
            $aulas[] = [
                'id' => is_numeric($fila->id) ? (int) $fila->id : 0,
                'nombre' => is_string($fila->nombre) ? $fila->nombre : '',
                'edificio' => is_string($fila->edificio) ? $fila->edificio : null,
                'piso' => is_string($fila->piso) ? $fila->piso : null,
            ];
        }

        return $aulas;
    }
}
