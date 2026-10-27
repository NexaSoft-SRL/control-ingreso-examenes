<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\EtiquetaTipoExamen;
use App\Modules\Ingreso\Application\Contracts\DetallePuertaGateway;
use App\Modules\Ingreso\Domain\Rules\EvaluarIngreso;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class EloquentDetallePuertaGateway implements DetallePuertaGateway
{
    public function __construct(
        private readonly EtiquetaTipoExamen $etiquetaTipo,
        private readonly EvaluarIngreso $reglas,
    ) {}

    public function aula(int $aulaId): ?array
    {
        $fila = DB::table('aulas as a')
            ->leftJoin('edificios as e', 'e.id', '=', 'a.edificio_id')
            ->where('a.id', $aulaId)
            ->first(['a.id', 'a.nombre', 'a.piso', 'a.edificio_id', 'e.nombre as edificio']);

        if ($fila === null) {
            return null;
        }

        return [
            'id' => self::entero(self::campo($fila, 'id')),
            'nombre' => self::texto(self::campo($fila, 'nombre')),
            'edificio' => self::textoONulo(self::campo($fila, 'edificio')),
            'piso' => self::textoONulo(self::campo($fila, 'piso')),
            'edificio_id' => self::campo($fila, 'edificio_id') === null ? null : self::entero(self::campo($fila, 'edificio_id')),
        ];
    }

    public function examen(int $examenId): ?array
    {
        $fila = DB::table('examenes as e')
            ->join('asignaturas as s', 's.id', '=', 'e.asignatura_id')
            ->where('e.id', $examenId)
            ->first(['e.id', 'e.tipo', 'e.hora_inicio', 's.nombre as asignatura']);

        if ($fila === null) {
            return null;
        }

        return [
            'id' => self::entero(self::campo($fila, 'id')),
            'asignatura' => self::texto(self::campo($fila, 'asignatura')),
            'tipo' => $this->tipo(self::texto(self::campo($fila, 'tipo'))),
            'hora' => self::hora(self::campo($fila, 'hora_inicio')),
        ];
    }

    public function ingresoDe(int $examenId, int $estudianteId): ?array
    {
        $fila = DB::table('ingresos as i')
            ->leftJoin('aulas as a', 'a.id', '=', 'i.aula_id')
            ->leftJoin('incidencias as x', static function (JoinClause $join): void {
                $join->on('x.ingreso_id', '=', 'i.id')->where('x.tipo', '=', 'EXPULSION');
            })
            ->where('i.examen_id', $examenId)
            ->where('i.estudiante_id', $estudianteId)
            ->first([
                'i.id',
                'i.registrado_en',
                'i.aula_id',
                'i.es_excepcion',
                'a.nombre as aula',
                'x.ocurrida_en as expulsado_en',
            ]);

        if ($fila === null) {
            return null;
        }

        return [
            'id' => self::entero(self::campo($fila, 'id')),
            'hora' => self::hora(self::campo($fila, 'registrado_en')),
            'registrado_en' => self::texto(self::campo($fila, 'registrado_en')),
            'aula_id' => self::campo($fila, 'aula_id') === null ? null : self::entero(self::campo($fila, 'aula_id')),
            'aula' => self::textoONulo(self::campo($fila, 'aula')),
            'expulsado_a' => self::campo($fila, 'expulsado_en') === null ? null : self::hora(self::campo($fila, 'expulsado_en')),
            'es_excepcion' => (bool) self::campo($fila, 'es_excepcion'),
        ];
    }

    public function examenCorrespondiente(
        int $estudianteId,
        string $fecha,
        array $excluidos,
    ): ?array {
        $fila = DB::table('examenes as e')
            ->join('asignaturas as s', 's.id', '=', 'e.asignatura_id')
            ->leftJoin('habilitaciones as h', static function (JoinClause $join) use ($estudianteId): void {
                $join->on('h.examen_id', '=', 'e.id')
                    ->where('h.estudiante_id', '=', $estudianteId);
            })
            ->leftJoin('aulas as a', 'a.id', '=', 'h.aula_id')
            ->whereDate('e.fecha', $fecha)
            ->whereNotIn('e.id', $excluidos === [] ? [0] : $excluidos)
            ->whereExists(static function (Builder $inscrito) use ($estudianteId): void {
                $inscrito->selectRaw('1')
                    ->from('examen_grupo as eg')
                    ->join('inscripciones as i', 'i.grupo_id', '=', 'eg.grupo_id')
                    ->whereColumn('eg.examen_id', 'e.id')
                    ->where('i.estudiante_id', $estudianteId);
            })
            ->orderBy('e.hora_inicio')
            ->orderBy('e.id')
            ->first(['s.nombre as asignatura', 'e.tipo', 'e.hora_inicio', 'a.nombre as aula']);

        if ($fila === null) {
            return null;
        }

        return [
            'texto' => sprintf(
                'Su examen: %s · %s · %s',
                self::texto(self::campo($fila, 'asignatura')),
                $this->tipo(self::texto(self::campo($fila, 'tipo'))),
                self::hora(self::campo($fila, 'hora_inicio')),
            ),
            'aula' => self::textoONulo(self::campo($fila, 'aula')),
        ];
    }

    public function examenesDelAula(int $aulaId, array $examenIds): array
    {
        if ($examenIds === []) {
            return [];
        }

        $filas = DB::table('examenes as e')
            ->join('asignaturas as s', 's.id', '=', 'e.asignatura_id')
            ->whereIn('e.id', $examenIds)
            ->orderBy('e.fecha')
            ->orderBy('e.hora_inicio')
            ->orderBy('e.id')
            ->select([
                'e.id',
                'e.tipo',
                'e.fecha',
                'e.hora_inicio',
                'e.duracion_minutos',
                'e.normas',
                's.nombre as asignatura',
            ])
            ->selectSub(
                DB::table('ingresos as i')
                    ->selectRaw('count(*)')
                    ->whereColumn('i.examen_id', 'e.id')
                    ->where('i.aula_id', $aulaId),
                'ingresados',
            )
            ->selectSub(
                DB::table('habilitaciones as h')
                    ->selectRaw('count(*)')
                    ->whereColumn('h.examen_id', 'e.id')
                    ->where('h.aula_id', $aulaId)
                    ->where('h.habilitado', true),
                'asignados',
            )
            ->selectSub(
                DB::table('habilitaciones as h')
                    ->selectRaw('count(*)')
                    ->whereColumn('h.examen_id', 'e.id')
                    ->where('h.aula_id', $aulaId)
                    ->where('h.habilitado', true)
                    ->whereNotExists(static function (Builder $ingreso): void {
                        $ingreso->selectRaw('1')
                            ->from('ingresos as i')
                            ->whereColumn('i.examen_id', 'h.examen_id')
                            ->whereColumn('i.estudiante_id', 'h.estudiante_id');
                    }),
                'faltan',
            )
            ->get();

        $marcadas = DB::table('examen_norma')
            ->whereIn('examen_id', $examenIds)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['examen_id', 'texto']);

        $porExamen = [];

        foreach ($marcadas as $norma) {
            $porExamen[self::entero(self::campo($norma, 'examen_id'))][] = self::texto(self::campo($norma, 'texto'));
        }

        $resultado = [];

        foreach ($filas as $fila) {
            $id = self::entero(self::campo($fila, 'id'));
            $inicio = self::hora(self::campo($fila, 'hora_inicio'));
            $fin = (new DateTimeImmutable('2000-01-01 '.$inicio))
                ->modify(sprintf('+%d minutes', self::entero(self::campo($fila, 'duracion_minutos'))))
                ->format('H:i');
            $normas = self::textoONulo(self::campo($fila, 'normas'));

            $resultado[] = [
                'id' => $id,
                'asignatura' => self::texto(self::campo($fila, 'asignatura')),
                'tipo' => $this->tipo(self::texto(self::campo($fila, 'tipo'))),
                'fecha' => substr(self::texto(self::campo($fila, 'fecha')), 0, 10),
                'hora' => $inicio,
                'hora_fin' => $fin,
                'ingresados' => self::entero(self::campo($fila, 'ingresados')),
                'asignados' => self::entero(self::campo($fila, 'asignados')),
                'faltan' => self::entero(self::campo($fila, 'faltan')),
                'normas' => $normas === null || trim($normas) === '' ? null : $normas,
                'normas_marcadas' => $porExamen[$id] ?? [],
            ];
        }

        return $resultado;
    }

    public function ingresosDelAula(
        int $aulaId,
        array $examenIds,
        ?string $buscar,
        int $limite,
    ): array {
        if ($examenIds === []) {
            return [];
        }

        $consulta = DB::table('ingresos as i')
            ->join('estudiantes as e', 'e.id', '=', 'i.estudiante_id')
            ->join('examenes as ex', 'ex.id', '=', 'i.examen_id')
            ->leftJoin('incidencias as x', static function (JoinClause $join): void {
                $join->on('x.ingreso_id', '=', 'i.id')->where('x.tipo', '=', 'EXPULSION');
            })
            ->leftJoin('usuarios as u', 'u.id', '=', 'i.registrado_por')
            ->whereIn('i.examen_id', $examenIds)
            ->where('i.aula_id', $aulaId);

        $texto = trim((string) $buscar);

        if ($texto !== '') {
            $patron = '%'.mb_strtolower($texto).'%';
            $consulta->where(static function (Builder $filtro) use ($patron): void {
                $filtro->whereRaw('lower(e.codigo_universitario) like ?', [$patron])
                    ->orWhereRaw("lower(e.apellidos || ' ' || e.nombres) like ?", [$patron])
                    ->orWhereRaw("lower(e.nombres || ' ' || e.apellidos) like ?", [$patron]);
            });
        }

        $filas = $consulta
            ->orderByDesc('i.registrado_en')
            ->orderByDesc('i.id')
            ->limit($limite)
            ->get([
                'i.id as ingreso_id',
                'i.examen_id',
                'i.estudiante_id',
                'e.codigo_universitario as codigo',
                'e.nombres',
                'e.apellidos',
                'i.aula_id',
                'i.registrado_en',
                'i.es_excepcion',
                'x.ocurrida_en as expulsado_en',
                'u.nombre as operador',
                'ex.hora_inicio',
            ]);

        $resultado = [];

        foreach ($filas as $fila) {
            $hora = self::hora(self::campo($fila, 'registrado_en'));
            $retraso = self::minutosDe($hora) - self::minutosDe(self::hora(self::campo($fila, 'hora_inicio')));

            $resultado[] = [
                'ingreso_id' => self::entero(self::campo($fila, 'ingreso_id')),
                'examen_id' => self::entero(self::campo($fila, 'examen_id')),
                'estudiante_id' => self::entero(self::campo($fila, 'estudiante_id')),
                'codigo' => self::texto(self::campo($fila, 'codigo')),
                'nombres' => self::texto(self::campo($fila, 'nombres')),
                'apellidos' => self::texto(self::campo($fila, 'apellidos')),
                'aula_id' => self::campo($fila, 'aula_id') === null ? null : self::entero(self::campo($fila, 'aula_id')),
                'registrado_en' => self::texto(self::campo($fila, 'registrado_en')),
                'hora' => self::hora(self::campo($fila, 'registrado_en')),
                'expulsado' => self::campo($fila, 'expulsado_en') !== null,
                'expulsado_a' => self::campo($fila, 'expulsado_en') === null ? null : self::hora(self::campo($fila, 'expulsado_en')),
                'es_excepcion' => (bool) self::campo($fila, 'es_excepcion'),
                'operador' => self::textoONulo(self::campo($fila, 'operador')),
                'tarde' => $this->reglas->minutosTarde($retraso),
            ];
        }

        return $resultado;
    }

    public function totalIngresos(int $aulaId, array $examenIds): int
    {
        if ($examenIds === []) {
            return 0;
        }

        return DB::table('ingresos')
            ->whereIn('examen_id', $examenIds)
            ->where('aula_id', $aulaId)
            ->count();
    }

    private function tipo(string $tipo): string
    {
        return $this->etiquetaTipo->de($tipo);
    }

    // '2026-10-26 08:39:20' o '08:39:20' → '08:39'.
    private static function hora(mixed $valor): string
    {
        $texto = self::texto($valor);

        if (preg_match('/(\d{2}):(\d{2})/', strlen($texto) > 10 ? substr($texto, 11) : $texto, $partes) === 1) {
            return $partes[1].':'.$partes[2];
        }

        return $texto;
    }

    // '08:39' → 519.
    private static function minutosDe(string $hora): int
    {
        [$h, $m] = array_map(intval(...), array_pad(explode(':', $hora), 2, '0'));

        return $h * 60 + $m;
    }

    private static function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }

    private static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    private static function textoONulo(mixed $valor): ?string
    {
        return is_scalar($valor) ? (string) $valor : null;
    }

    private static function campo(object $fila, string $nombre): mixed
    {
        return get_object_vars($fila)[$nombre] ?? null;
    }
}
