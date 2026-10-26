<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Ingreso\Application\Contracts\SeguimientoAulaGateway;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class EloquentSeguimientoAulaGateway implements SeguimientoAulaGateway
{
    private const POR_PAGINA = 25;

    public function __construct(
        private AlcanceExamenGateway $alcanceExamen,
        private BitacoraGateway $bitacora,
    ) {}

    public function aulasDelUsuario(int $usuarioId): array
    {
        $hoy = now()->toDateString();
        $esDocente = DB::table('docentes')
            ->where('user_id', $usuarioId)
            ->exists();

        $consulta = DB::table('examen_aula as ea')
            ->join('examenes as e', 'e.id', '=', 'ea.examen_id')
            ->join('aulas as a', 'a.id', '=', 'ea.aula_id')
            ->join('asignaturas as s', 's.id', '=', 'e.asignatura_id')
            ->whereDate('e.fecha', '>=', $hoy);

        if ($esDocente) {
            $consulta->where(function (Builder $consulta) use ($usuarioId): void {
                $consulta->where(function (Builder $propios) use ($usuarioId): void {
                    $propios->where('e.creado_por', $usuarioId)
                        ->orWhereExists(function (Builder $subconsulta) use ($usuarioId): void {
                            $subconsulta->selectRaw('1')
                                ->from('examen_grupo as eg')
                                ->join('grupos as g', 'g.id', '=', 'eg.grupo_id')
                                ->join('docentes as d', 'd.id', '=', 'g.docente_id')
                                ->whereColumn('eg.examen_id', 'e.id')
                                ->where('d.user_id', $usuarioId);
                        });
                })->orWhereExists(function (Builder $asignacion) use ($usuarioId): void {
                    $asignacion->selectRaw('1')
                        ->from('examen_aula_personal as eap')
                        ->whereColumn('eap.examen_id', 'ea.examen_id')
                        ->whereColumn('eap.aula_id', 'ea.aula_id')
                        ->where('eap.usuario_id', $usuarioId);
                });
            });
        } else {
            $consulta->join(
                'examen_aula_personal as eap',
                static function (JoinClause $join) use ($usuarioId): void {
                    $join->on('eap.examen_id', '=', 'ea.examen_id')
                        ->on('eap.aula_id', '=', 'ea.aula_id')
                        ->where('eap.usuario_id', '=', $usuarioId);
                },
            );
        }

        $filas = $consulta
            ->orderBy('e.fecha')
            ->orderBy('e.hora_inicio')
            ->orderByRaw("translate(lower(a.nombre), 'áéíóúüñ', 'aeiouun')")
            ->get([
                'e.id as examen_id',
                'e.fecha',
                'e.hora_inicio',
                'e.duracion_minutos',
                'e.tipo',
                's.nombre as asignatura',
                'ea.aula_id',
                'a.nombre as aula',
            ]);

        $resultado = [];

        foreach ($filas as $fila) {
            /**
             * @var object{
             *   examen_id: int,
             *   fecha: string,
             *   hora_inicio: string,
             *   duracion_minutos: int,
             *   tipo: string,
             *   asignatura: string,
             *   aula_id: int,
             *   aula: string
             * } $fila
             */
            $examenId = (int) $fila->examen_id;
            $aulaId = (int) $fila->aula_id;
            $resultado[] = [
                'examen_id' => $examenId,
                'aula_id' => $aulaId,
                'fecha' => (string) $fila->fecha,
                'hora' => substr((string) $fila->hora_inicio, 0, 5),
                'duracion_minutos' => (int) $fila->duracion_minutos,
                'tipo' => (string) $fila->tipo,
                'asignatura' => (string) $fila->asignatura,
                'aula' => (string) $fila->aula,
                'puede_asignar' => $esDocente
                    && $this->alcanceExamen->esDocenteDelExamen($usuarioId, $examenId),
            ];
        }

        return $resultado;
    }

    public function aulaPerteneceAExamen(int $examenId, int $aulaId): bool
    {
        return DB::table('examen_aula')
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->exists();
    }

    public function fechaDeExamen(int $examenId): ?string
    {
        $fecha = DB::table('examenes')
            ->where('id', $examenId)
            ->value('fecha');

        if ($fecha === null) {
            return null;
        }

        if (! is_string($fecha)) {
            throw new LogicException('La fecha del examen tiene un formato inesperado.');
        }

        return $fecha;
    }

    public function tieneAsignacion(int $usuarioId, int $examenId, int $aulaId): bool
    {
        return DB::table('examen_aula_personal')
            ->where('usuario_id', $usuarioId)
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->exists();
    }

    public function puedeVerAula(int $usuarioId, int $examenId, int $aulaId): bool
    {
        if ($this->alcanceExamen->esDocenteDelExamen($usuarioId, $examenId)) {
            return true;
        }

        return DB::table('examen_aula_personal')
            ->where('usuario_id', $usuarioId)
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->whereExists(function (Builder $consulta) use ($examenId): void {
                $consulta->selectRaw('1')
                    ->from('examenes')
                    ->where('id', $examenId)
                    ->whereDate('fecha', now()->toDateString());
            })
            ->exists();
    }

    public function detalle(
        int $examenId,
        int $aulaId,
        string $estado,
        string $busqueda,
        int $pagina,
    ): array {
        if (! in_array($estado, ['faltan', 'ingresaron'], true) || $pagina < 1) {
            throw new InvalidArgumentException('Parámetros de seguimiento inválidos.');
        }

        $examen = DB::table('examenes as e')
            ->join('asignaturas as s', 's.id', '=', 'e.asignatura_id')
            ->where('e.id', $examenId)
            ->first([
                'e.id',
                'e.fecha',
                'e.hora_inicio',
                'e.duracion_minutos',
                'e.tipo',
                's.nombre as asignatura',
            ]);

        /**
         * @var object{
         *   id: int,
         *   fecha: string,
         *   hora_inicio: string,
         *   duracion_minutos: int,
         *   tipo: string,
         *   asignatura: string
         * }|null $examen
         */
        $aula = DB::table('aulas as a')
            ->join('examen_aula as ea', 'ea.aula_id', '=', 'a.id')
            ->where('ea.examen_id', $examenId)
            ->where('ea.aula_id', $aulaId)
            ->first(['a.id', 'a.nombre']);

        /** @var object{id: int, nombre: string}|null $aula */
        if ($examen === null || $aula === null) {
            throw new InvalidArgumentException('Aula no encontrada.');
        }

        $asignados = $this->consultaHabilitados($examenId, $aulaId);
        $totalAsignados = (int) (clone $asignados)->count();

        $presentes = DB::table('ingresos as i')
            ->where('i.examen_id', $examenId)
            ->where('i.aula_id', $aulaId);
        $totalPresentes = (int) (clone $presentes)->count();

        if ($estado === 'faltan') {
            $registros = $this->consultaHabilitados($examenId, $aulaId)
                ->whereNotExists(function (Builder $consulta) use ($examenId): void {
                    $consulta->selectRaw('1')
                        ->from('ingresos as i')
                        ->whereColumn('i.estudiante_id', 'h.estudiante_id')
                        ->where('i.examen_id', $examenId);
                });
        } else {
            $registros = DB::table('ingresos as i')
                ->join('estudiantes as e', 'e.id', '=', 'i.estudiante_id')
                ->leftJoin('expulsiones as x', 'x.ingreso_id', '=', 'i.id')
                ->where('i.examen_id', $examenId)
                ->where('i.aula_id', $aulaId);
        }

        $buscar = $this->normalizar($busqueda);

        if ($buscar !== '') {
            foreach (preg_split('/\s+/u', $buscar, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $termino) {
                $patron = '%'.addcslashes($termino, '%_\\').'%';
                $registros->where(function (Builder $consulta) use ($patron): void {
                    foreach (['nombres', 'apellidos', 'codigo_universitario', 'documento_identidad'] as $columna) {
                        $consulta->orWhereRaw(
                            "translate(lower(e.{$columna}), 'áéíóúüñ', 'aeiouun') LIKE ?",
                            [$patron],
                        );
                    }
                });
            }
        }

        $total = (int) (clone $registros)->count();
        $ultima = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min($pagina, $ultima);
        $columnas = [
            'e.id as estudiante_id',
            'e.codigo_universitario as codigo',
            'e.nombres',
            'e.apellidos',
        ];

        if ($estado === 'faltan') {
            $columnas[] = 'h.aula_id';
            $columnas[] = DB::raw('NULL as registrado_en');
            $columnas[] = DB::raw('NULL as expulsado_en');
        } else {
            $columnas[] = 'i.aula_id';
            $columnas[] = 'i.registrado_en';
            $columnas[] = 'x.registrada_en as expulsado_en';
        }

        if ($estado === 'ingresaron') {
            $registros->orderByDesc('i.registrado_en')
                ->orderByDesc('i.id');
        } else {
            $registros->orderByRaw("translate(lower(e.apellidos), 'áéíóúüñ', 'aeiouun')")
                ->orderByRaw("translate(lower(e.nombres), 'áéíóúüñ', 'aeiouun')")
                ->orderBy('e.codigo_universitario');
        }

        $filas = $registros
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA)
            ->get($columnas);

        $gruposPorEstudiante = [];
        if ($filas->isNotEmpty()) {
            $grupos = DB::table('inscripciones as i')
                ->join('examen_grupo as eg', 'eg.grupo_id', '=', 'i.grupo_id')
                ->join('grupos as g', 'g.id', '=', 'i.grupo_id')
                ->where('eg.examen_id', $examenId)
                ->whereIn('i.estudiante_id', $filas->pluck('estudiante_id')->unique()->all())
                ->orderBy('g.codigo')
                ->get(['i.estudiante_id', 'g.codigo']);

            foreach ($grupos as $grupo) {
                /** @var object{estudiante_id: int|string, codigo: string} $grupo */
                $gruposPorEstudiante[(int) $grupo->estudiante_id][] = 'Grupo '.(string) $grupo->codigo;
            }
        }

        $estudiantes = [];

        foreach ($filas as $fila) {
            /**
             * @var object{
             *   estudiante_id: int,
             *   codigo: string,
             *   nombres: string,
             *   apellidos: string,
             *   aula_id: int,
             *   registrado_en: string|null,
             *   expulsado_en: string|null
             * } $fila
             */
            $estudiantes[] = [
                'estudiante_id' => (int) $fila->estudiante_id,
                'codigo' => (string) $fila->codigo,
                'nombres' => (string) $fila->nombres,
                'apellidos' => (string) $fila->apellidos,
                'grupos' => $gruposPorEstudiante[(int) $fila->estudiante_id] ?? [],
                'aula_id' => (int) $fila->aula_id,
                'estado' => $estado === 'faltan'
                    ? 'Ausente'
                    : ($fila->expulsado_en === null ? 'Presente' : 'Expulsado'),
                'registrado_en' => $estado === 'ingresaron'
                    ? (string) $fila->registrado_en
                    : null,
                'expulsado_en' => $estado === 'ingresaron' && $fila->expulsado_en !== null
                    ? (string) $fila->expulsado_en
                    : null,
            ];
        }

        $incidencias = DB::table('incidencias_aula as i')
            ->join('usuarios as u', 'u.id', '=', 'i.reportada_por')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->leftJoin('estudiantes as e', 'e.id', '=', 'i.estudiante_id')
            ->leftJoin('usuarios as atendio', 'atendio.id', '=', 'i.atendida_por')
            ->where('i.examen_id', $examenId)
            ->where('i.aula_id', $aulaId)
            ->orderByDesc('i.reportada_en')
            ->orderByDesc('i.id')
            ->limit(50)
            ->get([
                'i.id',
                'i.tipo',
                'i.detalle',
                'i.reportada_en',
                'i.atendida_en',
                'i.estudiante_id',
                'e.nombres',
                'e.apellidos',
                'u.nombre as reportante',
                'r.name as rol_reportante',
                'atendio.nombre as atendida_por',
            ])
            ->map(static function (object $fila): array {
                /**
                 * @var object{
                 *   id: int,
                 *   tipo: string,
                 *   detalle: string,
                 *   reportada_en: string,
                 *   atendida_en: string|null,
                 *   estudiante_id: int|null,
                 *   nombres: string|null,
                 *   apellidos: string|null,
                 *   reportante: string,
                 *   rol_reportante: string,
                 *   atendida_por: string|null
                 * } $fila
                 */

                return [
                    'id' => (int) $fila->id,
                    'tipo' => (string) $fila->tipo,
                    'detalle' => (string) $fila->detalle,
                    'reportada_en' => (string) $fila->reportada_en,
                    'estudiante' => $fila->estudiante_id === null
                        ? null
                        : trim((string) $fila->nombres.' '.(string) $fila->apellidos),
                    'reportante' => (string) $fila->reportante,
                    'rol_reportante' => (string) $fila->rol_reportante,
                    'atendida_en' => $fila->atendida_en === null
                        ? null
                        : (string) $fila->atendida_en,
                    'atendida_por' => $fila->atendida_por,
                ];
            })
            ->values()
            ->all();
        $incidencias = array_values($incidencias);
        $totalIncidencias = (int) DB::table('incidencias_aula')
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->count();

        return [
            'examen' => [
                'id' => (int) $examen->id,
                'fecha' => (string) $examen->fecha,
                'hora' => substr((string) $examen->hora_inicio, 0, 5),
                'duracion_minutos' => (int) $examen->duracion_minutos,
                'tipo' => (string) $examen->tipo,
                'asignatura' => (string) $examen->asignatura,
                'hora_servidor' => now()->toIso8601String(),
            ],
            'aula' => [
                'id' => (int) $aula->id,
                'nombre' => (string) $aula->nombre,
            ],
            'cifras' => [
                'ingresaron' => $totalPresentes,
                'faltan' => (int) $this->consultaHabilitados($examenId, $aulaId)
                    ->whereNotExists(function (Builder $consulta) use ($examenId): void {
                        $consulta->selectRaw('1')
                            ->from('ingresos as i')
                            ->whereColumn('i.estudiante_id', 'h.estudiante_id')
                            ->where('i.examen_id', $examenId);
                    })
                    ->count(),
                'asignados' => $totalAsignados,
                'incidencias' => $totalIncidencias,
            ],
            'estudiantes' => $estudiantes,
            'incidencias' => $incidencias,
            'pagina' => [
                'actual' => $pagina,
                'ultima' => $ultima,
                'por_pagina' => self::POR_PAGINA,
                'total' => $total,
            ],
        ];
    }

    public function ubicacionIncidencia(int $incidenciaId): ?array
    {
        $fila = DB::table('incidencias_aula')
            ->where('id', $incidenciaId)
            ->first(['examen_id', 'aula_id']);

        /** @var object{examen_id: int, aula_id: int}|null $fila */
        return $fila === null
            ? null
            : ['examen_id' => (int) $fila->examen_id, 'aula_id' => (int) $fila->aula_id];
    }

    public function marcarIncidenciaAtendida(
        int $incidenciaId,
        int $usuarioId,
    ): bool {
        return DB::transaction(function () use ($incidenciaId, $usuarioId): bool {
            $ubicacion = DB::table('incidencias_aula as incidencia')
                ->join('aulas as aula', 'aula.id', '=', 'incidencia.aula_id')
                ->where('incidencia.id', $incidenciaId)
                ->first(['incidencia.examen_id', 'aula.nombre as aula']);

            if ($ubicacion === null) {
                return false;
            }

            $actualizada = DB::table('incidencias_aula')
                ->where('id', $incidenciaId)
                ->whereNull('atendida_en')
                ->update([
                    'atendida_por' => $usuarioId,
                    'atendida_en' => now(),
                ]) === 1;

            if (! $actualizada) {
                return false;
            }

            $columnasUbicacion = get_object_vars($ubicacion);
            $aula = $columnasUbicacion['aula'] ?? null;
            $examenId = filter_var(
                $columnasUbicacion['examen_id'] ?? null,
                FILTER_VALIDATE_INT,
            );

            if (! is_string($aula) || ! is_int($examenId) || $examenId <= 0) {
                throw new LogicException('La ubicacion de la incidencia no es valida.');
            }

            $this->bitacora->registrar(
                $usuarioId,
                'INCIDENCIA_AULA_ATENDIDA',
                'incidencias_aula',
                $incidenciaId,
                sprintf('Incidencia de %s, examen %d.', $aula, $examenId),
            );

            return true;
        });
    }

    private function consultaHabilitados(int $examenId, int $aulaId): Builder
    {
        return DB::table('habilitaciones as h')
            ->join('estudiantes as e', 'e.id', '=', 'h.estudiante_id')
            ->where('h.examen_id', $examenId)
            ->where('h.aula_id', $aulaId)
            ->where('h.habilitado', true)
            ->whereExists(function (Builder $consulta) use ($examenId): void {
                $consulta->selectRaw('1')
                    ->from('inscripciones as i')
                    ->join('examen_grupo as eg', 'eg.grupo_id', '=', 'i.grupo_id')
                    ->whereColumn('i.estudiante_id', 'h.estudiante_id')
                    ->whereColumn('eg.examen_id', 'h.examen_id')
                    ->where('eg.examen_id', $examenId);
            });
    }

    private function normalizar(string $texto): string
    {
        return strtr(mb_strtolower(trim($texto)), [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }
}
