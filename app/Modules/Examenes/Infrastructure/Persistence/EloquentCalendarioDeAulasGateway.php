<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\CalendarioDeAulasGateway;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lee por nombre de tabla, como el resto de las consultas del modulo: los
 * horarios (clases) con sus grupos y periodos, y los examenes con sus aulas.
 * Es la misma ocupacion que el asistente avisa como «Coincidencia» y
 * «ocupada», vista por semana.
 */
final class EloquentCalendarioDeAulasGateway implements CalendarioDeAulasGateway
{
    use ConvierteFilas;

    /**
     * Los dias del horario, de lunes a sabado (el domingo no hay clases).
     */
    private const DIAS = ['LU', 'MA', 'MI', 'JU', 'VI', 'SA'];

    public function aulasDelEdificio(int $edificioId): array
    {
        $ids = [];

        foreach (DB::table('aulas')->where('edificio_id', $edificioId)->orderBy('nombre')->pluck('id') as $id) {
            $ids[] = $this->entero($id);
        }

        return $ids;
    }

    public function ocupacion(array $aulaIds, string $desde, string $hasta, int $usuarioId): array
    {
        if ($aulaIds === []) {
            return [];
        }

        $clases = $this->clases($aulaIds, $desde, $hasta);
        $examenes = $this->examenes($aulaIds, $desde, $hasta, $usuarioId);
        $ocupacion = [];

        // Cada aula pedida figura, tambien la que esta libre.
        foreach ($aulaIds as $id) {
            $ocupacion[$id] = ['clases' => $clases[$id] ?? [], 'examenes' => $examenes[$id] ?? []];
        }

        return $ocupacion;
    }

    /**
     * Cada clase del horario, una vez por dia del rango en que su periodo
     * esta en curso.
     *
     * @param  list<int>  $aulaIds
     * @return array<int, list<array{fecha: string, inicio: string, fin: string, asignatura: string, grupo: string, docente: ?string, auxiliatura: bool}>>
     */
    private function clases(array $aulaIds, string $desde, string $hasta): array
    {
        $periodos = [];

        foreach (DB::table('periodos')
            ->where('fecha_inicio', '<=', $hasta)
            ->where('fecha_fin', '>=', $desde)
            ->get(['id', 'fecha_inicio', 'fecha_fin']) as $fila) {
            $columnas = $this->columnas($fila);
            $periodos[$this->entero($columnas['id'] ?? null)] = [
                $this->fecha($columnas['fecha_inicio'] ?? null),
                $this->fecha($columnas['fecha_fin'] ?? null),
            ];
        }

        if ($periodos === []) {
            return [];
        }

        $filas = DB::table('horarios as horario')
            ->join('grupos as grupo', 'grupo.id', '=', 'horario.grupo_id')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'grupo.asignatura_id')
            ->leftJoin('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
            ->whereIn('grupo.periodo_id', array_keys($periodos))
            ->whereIn('horario.aula_id', $aulaIds)
            ->orderBy('horario.hora_inicio')
            ->orderBy('asignatura.nombre')
            ->select([
                'horario.aula_id',
                'horario.dia',
                'horario.hora_inicio',
                'horario.hora_fin',
                'horario.es_auxiliatura',
                'grupo.periodo_id',
                'grupo.codigo',
                'asignatura.nombre as asignatura',
                'docente.nombre_completo as docente',
            ])
            ->get();

        $clases = [];

        foreach ($this->fechas($desde, $hasta) as $fecha) {
            $dia = self::DIAS[(int) date('N', (int) strtotime($fecha)) - 1] ?? null;

            if ($dia === null) {
                continue;
            }

            foreach ($filas as $fila) {
                $columnas = $this->columnas($fila);
                [$inicioPeriodo, $finPeriodo] = $periodos[$this->entero($columnas['periodo_id'] ?? null)] ?? ['9999-12-31', '0000-01-01'];

                if ($this->cadena($columnas['dia'] ?? null) !== $dia || $fecha < $inicioPeriodo || $fecha > $finPeriodo) {
                    continue;
                }

                $clases[$this->entero($columnas['aula_id'] ?? null)][] = [
                    'fecha' => $fecha,
                    'inicio' => $this->hora($columnas['hora_inicio'] ?? null),
                    'fin' => $this->hora($columnas['hora_fin'] ?? null),
                    'asignatura' => $this->cadena($columnas['asignatura'] ?? null),
                    'grupo' => $this->cadena($columnas['codigo'] ?? null),
                    'docente' => $this->texto($columnas['docente'] ?? null),
                    'auxiliatura' => $this->booleano($columnas['es_auxiliatura'] ?? null),
                ];
            }
        }

        return $clases;
    }

    /**
     * @param  list<int>  $aulaIds
     * @return array<int, list<array{id: int|null, fecha: string, inicio: string, fin: string, asignatura: string, tipo: string, docente: ?string, propio: bool}>>
     */
    private function examenes(array $aulaIds, string $desde, string $hasta, int $usuarioId): array
    {
        $filas = DB::table('examenes as examen')
            ->join('examen_aula as asignada', 'asignada.examen_id', '=', 'examen.id')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->join('users as creador', 'creador.id', '=', 'examen.creado_por')
            ->leftJoin('docentes as docente_creador', 'docente_creador.user_id', '=', 'creador.id')
            ->whereIn('asignada.aula_id', $aulaIds)
            ->whereDate('examen.fecha', '>=', $desde)
            ->whereDate('examen.fecha', '<=', $hasta)
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->select([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.duracion_minutos',
                'asignada.aula_id',
                'asignatura.nombre as asignatura',
                'creador.name as creador_nombre',
                'docente_creador.nombre_completo as creador_docente',
            ])
            ->get();

        $propios = $this->propios(array_values(array_unique(array_map(
            fn (object $fila): int => $this->entero($this->columnas($fila)['id'] ?? null),
            $filas->all(),
        ))), $usuarioId);

        $examenes = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $id = $this->entero($columnas['id'] ?? null);
            $inicio = $this->hora($columnas['hora_inicio'] ?? null);
            $fin = ((int) substr($inicio, 0, 2)) * 60 + (int) substr($inicio, 3, 2) + $this->entero($columnas['duracion_minutos'] ?? null);
            $propio = in_array($id, $propios, true);

            $examenes[$this->entero($columnas['aula_id'] ?? null)][] = [
                'id' => $propio ? $id : null,
                'fecha' => $this->fecha($columnas['fecha'] ?? null),
                'inicio' => $inicio,
                'fin' => sprintf('%02d:%02d', intdiv($fin, 60) % 24, $fin % 60),
                'asignatura' => $this->cadena($columnas['asignatura'] ?? null),
                'tipo' => TipoExamen::tryFrom($this->cadena($columnas['tipo'] ?? null))?->etiqueta() ?? $this->cadena($columnas['tipo'] ?? null),
                'docente' => $this->texto($columnas['creador_docente'] ?? null)
                    ?? $this->texto($columnas['creador_nombre'] ?? null),
                'propio' => $propio,
            ];
        }

        return $examenes;
    }

    /**
     * Los examenes de la cuenta: los que registro o los que incluyen un
     * grupo suyo.
     *
     * @param  list<int>  $examenIds
     * @return list<int>
     */
    private function propios(array $examenIds, int $usuarioId): array
    {
        if ($examenIds === []) {
            return [];
        }

        $ids = DB::table('examenes as examen')
            ->whereIn('examen.id', $examenIds)
            ->where(function (Builder $propio) use ($usuarioId): void {
                $propio->where('examen.creado_por', $usuarioId)
                    ->orWhereExists(function (Builder $incluido) use ($usuarioId): void {
                        $incluido->selectRaw('1')
                            ->from('examen_grupo as eg')
                            ->join('grupos as grupo', 'grupo.id', '=', 'eg.grupo_id')
                            ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
                            ->whereColumn('eg.examen_id', 'examen.id')
                            ->where('docente.user_id', $usuarioId);
                    });
            })
            ->pluck('examen.id');

        $propios = [];

        foreach ($ids as $id) {
            $propios[] = $this->entero($id);
        }

        return $propios;
    }

    /**
     * Cada dia `AAAA-MM-DD` del rango, los dos inclusive.
     *
     * @return list<string>
     */
    private function fechas(string $desde, string $hasta): array
    {
        $fechas = [];

        for ($dia = new DateTimeImmutable($desde), $fin = new DateTimeImmutable($hasta); $dia <= $fin; $dia = $dia->modify('+1 day')) {
            $fechas[] = $dia->format('Y-m-d');
        }

        return $fechas;
    }
}
