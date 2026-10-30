<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Persistence;

use App\Modules\Reportes\Application\Contracts\ConsultaReportesGateway;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

/**
 * @phpstan-import-type ExamenReporte from ConsultaReportesGateway
 * @phpstan-import-type FilaReporteEstudiante from ConsultaReportesGateway
 * @phpstan-import-type FilaReporteIncidencia from ConsultaReportesGateway
 */
final class EloquentConsultaReportesGateway implements ConsultaReportesGateway
{
    private const TIPOS_EXAMEN = [
        'PRIMER_PARCIAL' => 'Primer parcial',
        'SEGUNDO_PARCIAL' => 'Segundo parcial',
        'FINAL' => 'Examen final',
        'SEGUNDA_INSTANCIA' => 'Segunda instancia',
        'MESA' => 'Examen de mesa',
    ];

    private const CAUSAS_RECHAZO = [
        'NO_INSCRITO' => 'No inscrito',
        'NO_HABILITADO' => 'No habilitado',
        'NO_REGISTRADO' => 'Sin registro',
        'QR_NO_VALIDO' => 'Código QR no válido',
        'EXPULSADO' => 'Expulsado',
        'NO_CORRESPONDE' => 'No corresponde',
        'OTRA_AULA' => 'Otra aula',
    ];

    private const TIPOS_INCIDENCIA = [
        'INTENTO_NO_AUTORIZADO' => 'Intento rechazado',
        'SUPLANTACION' => 'Suplantación de identidad',
        'MATERIAL_NO_PERMITIDO' => 'Material no permitido',
        'DISPOSITIVO_NO_PERMITIDO' => 'Dispositivo no permitido',
        'INGRESO_REPETIDO' => 'Ingreso repetido',
        'EXPULSION' => 'Expulsión',
        'CAMBIO_AULA' => 'Cambio de aula',
        'OTRA' => 'Otra',
    ];

    public function listar(
        int $usuarioId,
        array $periodoIds,
        ?int $periodoId,
        ?int $asignaturaId,
        int $pagina,
    ): array {
        $base = $this->examenesVisibles($usuarioId, $periodoIds);

        if ($periodoId !== null) {
            $base->where('examen.periodo_id', $periodoId);
        }

        $periodosDeAsignaturas = $periodoId === null ? $periodoIds : [$periodoId];
        $asignaturas = $this->asignaturasVisibles($usuarioId, $periodosDeAsignaturas);

        if ($asignaturaId !== null) {
            $base->where('examen.asignatura_id', $asignaturaId);
        }

        $total = (clone $base)->count('examen.id');
        $rendidos = (clone $base)->where($this->yaInicio())->count('examen.id');
        $porPagina = 8;
        $ultima = max(1, (int) ceil($total / $porPagina));
        $pagina = min($pagina, $ultima);

        $filas = $base
            ->leftJoin('periodos as periodo', 'periodo.id', '=', 'examen.periodo_id')
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->offset(($pagina - 1) * $porPagina)
            ->limit($porPagina)
            ->get([
                'examen.id',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.tipo',
                'examen.periodo_id',
                'periodo.codigo as periodo',
                'examen.asignatura_id',
                'asignatura.codigo as asignatura_codigo',
                'asignatura.nombre as asignatura_nombre',
            ]);

        $data = [];

        foreach ($filas as $fila) {
            $fila = $this->campos($fila);
            $habilitados = $this->contarHabilitados((int) $fila['id']);
            $ingresaron = $this->contarIngresos((int) $fila['id']);
            $rendido = $this->inicio($fila['fecha'], $fila['hora_inicio']) <= $this->ahora();

            $data[] = [
                'id' => (int) $fila['id'],
                'periodo' => ['id' => (int) $fila['periodo_id'], 'codigo' => (string) $fila['periodo']],
                'fecha' => substr((string) $fila['fecha'], 0, 10),
                'hora' => substr((string) $fila['hora_inicio'], 0, 5),
                'tipo' => $this->tipoTexto((string) $fila['tipo']),
                'asignatura' => [
                    'id' => (int) $fila['asignatura_id'],
                    'codigo' => (string) $fila['asignatura_codigo'],
                    'nombre' => (string) $fila['asignatura_nombre'],
                ],
                'inscritos' => $this->contarInscritos((int) $fila['id']),
                'habilitados' => $habilitados,
                'ingresaron' => $ingresaron,
                'asistencia_porcentaje' => $habilitados === 0
                    ? 0
                    : (int) round(($ingresaron / $habilitados) * 100),
                'rendido' => $rendido,
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'paginas' => $ultima,
                'rendidos' => $rendidos,
            ],
            'asignaturas' => $asignaturas,
        ];
    }

    public function existe(int $examenId): bool
    {
        return DB::table('examenes')->where('id', $examenId)->exists();
    }

    public function perteneceAlDocente(int $examenId, int $usuarioId): bool
    {
        return $this->examenesVisibles($usuarioId, null)
            ->where('examen.id', $examenId)
            ->exists();
    }

    public function incidencias(int $examenId, int $pagina): array
    {
        $examen = DB::table('examenes')
            ->where('id', $examenId)
            ->first(['fecha', 'hora_inicio', 'duracion_minutos']);

        if ($examen === null) {
            return [
                'data' => [],
                'meta' => ['total' => 0, 'pagina' => 1, 'por_pagina' => 8, 'paginas' => 1],
            ];
        }

        $examen = $this->campos($examen);
        $consulta = $this->consultaIncidencias(
            $examenId,
            $this->inicio($examen['fecha'], $examen['hora_inicio']),
            (int) $examen['duracion_minutos'],
        );
        $total = (clone $consulta)->count('incidencia.id');
        $porPagina = 8;
        $ultima = max(1, (int) ceil($total / $porPagina));
        $pagina = min($pagina, $ultima);
        $filas = $consulta
            ->offset(($pagina - 1) * $porPagina)
            ->limit($porPagina)
            ->get();

        return [
            'data' => $this->convertirIncidencias($filas),
            'meta' => [
                'total' => $total,
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'paginas' => $ultima,
            ],
        ];
    }

    /**
     * @return ExamenReporte|null
     */
    public function reporte(int $examenId): ?array
    {
        $examen = DB::table('examenes as examen')
            ->join('periodos as periodo', 'periodo.id', '=', 'examen.periodo_id')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->where('examen.id', $examenId)
            ->first([
                'examen.id',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.duracion_minutos',
                'examen.tipo',
                'periodo.codigo as periodo',
                'asignatura.codigo as asignatura_codigo',
                'asignatura.nombre as asignatura_nombre',
            ]);

        if ($examen === null) {
            return null;
        }

        $examen = $this->campos($examen);
        $inicio = $this->inicio($examen['fecha'], $examen['hora_inicio']);
        $iniciado = $inicio <= $this->ahora();
        $estudiantes = $this->estudiantes((int) $examen['id']);
        $asistencia = array_values(array_filter(
            $estudiantes,
            static fn (array $estudiante): bool => $estudiante['ingreso_id'] !== null,
        ));
        $ausentes = array_values(array_filter(
            $estudiantes,
            static fn (array $estudiante): bool => $estudiante['habilitado'] === true
                && $estudiante['ingreso_id'] === null,
        ));
        $noHabilitados = array_values(array_filter(
            $estudiantes,
            static fn (array $estudiante): bool => $estudiante['habilitado'] === false,
        ));
        $incidencias = $this->listarTodasIncidencias(
            (int) $examen['id'],
            $inicio,
            (int) $examen['duracion_minutos'],
        );
        $habilitados = count(array_filter(
            $estudiantes,
            static fn (array $estudiante): bool => $estudiante['habilitado'] === true,
        ));

        usort(
            $asistencia,
            static fn (array $a, array $b): int => strcmp($a['registrado_en'] ?? '', $b['registrado_en'] ?? ''),
        );

        return [
            'id' => (int) $examen['id'],
            'periodo' => (string) $examen['periodo'],
            'fecha' => substr((string) $examen['fecha'], 0, 10),
            'hora' => substr((string) $examen['hora_inicio'], 0, 5),
            'tipo' => $this->tipoTexto((string) $examen['tipo']),
            'asignatura' => [
                'codigo' => (string) $examen['asignatura_codigo'],
                'nombre' => (string) $examen['asignatura_nombre'],
            ],
            'rendido' => $iniciado,
            'inscritos' => count($estudiantes),
            'ingresaron' => count($asistencia),
            'habilitados' => $habilitados,
            'ausentes' => count($ausentes),
            'no_habilitados' => count($noHabilitados),
            'incidencias' => count($incidencias),
            'rechazados' => count(array_filter(
                $incidencias,
                static fn (array $incidencia): bool => $incidencia['rechazado'],
            )),
            'asistencia_porcentaje' => $habilitados === 0
                ? 0
                : (int) round((count($asistencia) / $habilitados) * 100),
            'muestras' => [
                'asistencia' => array_slice($asistencia, 0, 5),
                'ausentes' => array_slice($ausentes, 0, 5),
                'incidencias' => array_slice($incidencias, 0, 5),
            ],
            'totales' => [
                'asistencia' => count($asistencia),
                'ausentes' => count($ausentes),
                'incidencias' => count($incidencias),
            ],
            'filas' => [
                'asistencia' => $asistencia,
                'ausentes' => $ausentes,
                'incidencias' => $incidencias,
            ],
        ];
    }

    /**
     * @param  list<int>|null  $periodoIds
     */
    private function examenesVisibles(int $usuarioId, ?array $periodoIds): Builder
    {
        $consulta = DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->where(function (Builder $alcance) use ($usuarioId): void {
                $alcance->where('examen.creado_por', $usuarioId)
                    ->orWhereExists(static function (Builder $grupo) use ($usuarioId): void {
                        $grupo->selectRaw('1')
                            ->from('examen_grupo as incluido')
                            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
                            ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
                            ->whereColumn('incluido.examen_id', 'examen.id')
                            ->where('docente.user_id', $usuarioId);
                    });
            });

        if ($periodoIds !== null) {
            $consulta->whereIn('examen.periodo_id', $periodoIds === [] ? [0] : $periodoIds);
        }

        return $consulta;
    }

    /**
     * @param  list<int>  $periodoIds
     * @return list<array{id: int, codigo: string, nombre: string}>
     */
    private function asignaturasVisibles(int $usuarioId, array $periodoIds): array
    {
        if ($periodoIds === []) {
            return [];
        }

        $filas = $this->examenesVisibles($usuarioId, $periodoIds)
            ->distinct()
            ->orderBy('asignatura.nombre')
            ->get([
                'asignatura.id',
                'asignatura.codigo',
                'asignatura.nombre',
            ]);

        $asignaturas = [];

        foreach ($filas as $fila) {
            $fila = $this->campos($fila);
            $asignaturas[] = [
                'id' => (int) $fila['id'],
                'codigo' => (string) $fila['codigo'],
                'nombre' => (string) $fila['nombre'],
            ];
        }

        return $asignaturas;
    }

    private function contarInscritos(int $examenId): int
    {
        return (int) DB::table('examen_grupo as incluido')
            ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
            ->where('incluido.examen_id', $examenId)
            ->distinct()
            ->count('inscripcion.estudiante_id');
    }

    private function contarHabilitados(int $examenId): int
    {
        return (int) DB::table('habilitaciones')
            ->where('examen_id', $examenId)
            ->where('habilitado', true)
            ->count();
    }

    private function contarIngresos(int $examenId): int
    {
        return (int) DB::table('ingresos')->where('examen_id', $examenId)->count();
    }

    /**
     * @return list<FilaReporteEstudiante>
     */
    private function estudiantes(int $examenId): array
    {
        $filas = DB::table('examen_grupo as incluido')
            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
            ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'grupo.id')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'inscripcion.estudiante_id')
            ->leftJoin('carreras as carrera', 'carrera.id', '=', 'estudiante.carrera_id')
            ->leftJoin('habilitaciones as habilitacion', static function (JoinClause $join): void {
                $join->on('habilitacion.estudiante_id', '=', 'estudiante.id')
                    ->on('habilitacion.examen_id', '=', 'incluido.examen_id');
            })
            ->leftJoin('aulas as aula', 'aula.id', '=', 'habilitacion.aula_id')
            ->leftJoin('ingresos as ingreso', static function (JoinClause $join): void {
                $join->on('ingreso.estudiante_id', '=', 'estudiante.id')
                    ->on('ingreso.examen_id', '=', 'incluido.examen_id');
            })
            ->leftJoin('incidencias as expulsion', static function (JoinClause $join): void {
                $join->on('expulsion.ingreso_id', '=', 'ingreso.id')
                    ->where('expulsion.tipo', '=', 'EXPULSION');
            })
            ->where('incluido.examen_id', $examenId)
            ->orderBy('estudiante.apellidos')
            ->orderBy('estudiante.nombres')
            ->get([
                'estudiante.id',
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
                'carrera.nombre as carrera',
                'grupo.codigo as grupo',
                'habilitacion.habilitado',
                'aula.nombre as aula',
                'ingreso.id as ingreso_id',
                'ingreso.registrado_en',
                'ingreso.medio_identificacion',
                'ingreso.es_excepcion',
                'expulsion.id as expulsion_id',
            ]);

        $estudiantes = [];
        $gruposPorEstudiante = [];

        foreach ($filas as $fila) {
            $fila = $this->campos($fila);
            $id = (int) $fila['id'];
            $estudiantes[$id] ??= [
                'codigo' => (string) $fila['codigo_universitario'],
                'estudiante' => trim((string) $fila['apellidos'].' '.(string) $fila['nombres']),
                'apellidos' => (string) $fila['apellidos'],
                'nombres' => (string) $fila['nombres'],
                'carrera' => $fila['carrera'] === null ? 'Sin carrera' : (string) $fila['carrera'],
                'habilitado' => $fila['habilitado'] === null ? null : (bool) $fila['habilitado'],
                'aula' => $fila['aula'] === null ? 'Sin aula' : (string) $fila['aula'],
                'ingreso_id' => $fila['ingreso_id'] === null ? null : (int) $fila['ingreso_id'],
                'registrado_en' => $fila['registrado_en'] === null ? null : (string) $fila['registrado_en'],
                'hora' => $fila['registrado_en'] === null ? null : substr((string) $fila['registrado_en'], 11, 5),
                'medio' => $this->medioTexto($fila['medio_identificacion']),
                'excepcion' => (bool) $fila['es_excepcion'],
                'expulsado' => $fila['expulsion_id'] !== null,
            ];

            $gruposPorEstudiante[$id][(string) $fila['grupo']] = true;
            $estudiantes[$id]['expulsado'] = $estudiantes[$id]['expulsado'] || $fila['expulsion_id'] !== null;
        }

        $resultado = [];

        foreach ($estudiantes as $id => $estudiante) {
            $estudiante['grupo'] = implode(', ', array_keys($gruposPorEstudiante[$id]));
            $estudiante['situacion'] = $estudiante['expulsado']
                ? 'Expulsado'
                : ($estudiante['excepcion'] ? 'Excepción' : 'Rindió');
            $resultado[] = $estudiante;
        }

        return $resultado;
    }

    /**
     * @return list<FilaReporteIncidencia>
     */
    private function listarTodasIncidencias(
        int $examenId,
        DateTimeImmutable $inicio,
        int $duracionMinutos,
    ): array {
        return $this->convertirIncidencias(
            $this->consultaIncidencias($examenId, $inicio, $duracionMinutos)->get(),
        );
    }

    private function consultaIncidencias(
        int $examenId,
        DateTimeImmutable $inicio,
        int $duracionMinutos,
    ): Builder {
        $aulas = DB::table('examen_aula')
            ->where('examen_id', $examenId)
            ->pluck('aula_id')
            ->map(fn (mixed $id): int => $this->entero($id, 'aula_id'))
            ->all();

        $fin = $inicio->modify(sprintf('+%d minutes', $duracionMinutos));
        $apertura = $inicio->modify('-1 hour');

        return DB::table('incidencias as incidencia')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'incidencia.aula_id')
            ->leftJoin('estudiantes as estudiante', 'estudiante.id', '=', 'incidencia.estudiante_id')
            ->leftJoin('usuarios as usuario', 'usuario.id', '=', 'incidencia.reportada_por')
            ->leftJoin('accesos_codigo_aula as acceso', 'acceso.id', '=', 'incidencia.acceso_codigo_aula_id')
            ->where(static function (Builder $alcance) use ($examenId, $aulas, $apertura, $fin): void {
                $alcance->where('incidencia.examen_id', $examenId);

                if ($aulas !== []) {
                    $alcance->orWhere(static function (Builder $sinExamen) use ($aulas, $apertura, $fin): void {
                        $sinExamen->whereNull('incidencia.examen_id')
                            ->whereIn('incidencia.aula_id', $aulas)
                            ->where('incidencia.ocurrida_en', '>=', $apertura->format('Y-m-d H:i:s'))
                            ->where('incidencia.ocurrida_en', '<=', $fin->format('Y-m-d H:i:s'));
                    });
                }
            })
            ->orderBy('incidencia.ocurrida_en')
            ->orderBy('incidencia.id')
            ->select([
                'incidencia.id',
                'incidencia.tipo',
                'incidencia.origen',
                'incidencia.causa',
                'incidencia.detalle',
                'incidencia.ocurrida_en',
                'incidencia.estudiante_texto',
                'incidencia.atendida_en',
                'aula.nombre as aula',
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
                'usuario.nombre as usuario',
                'acceso.nombre_colaborador as colaborador',
            ]);
    }

    /**
     * @param  iterable<object>  $filas
     * @return list<FilaReporteIncidencia>
     */
    private function convertirIncidencias(iterable $filas): array
    {
        $resultado = [];

        foreach ($filas as $fila) {
            $fila = $this->campos($fila);
            $tipo = (string) $fila['tipo'];
            $causa = self::CAUSAS_RECHAZO[(string) $fila['causa']] ?? null;
            $codigo = $fila['codigo_universitario'] === null ? null : (string) $fila['codigo_universitario'];
            $nombre = $codigo === null
                ? ($fila['estudiante_texto'] === null ? 'Sin identificar' : (string) $fila['estudiante_texto'])
                : trim((string) $fila['apellidos'].' '.(string) $fila['nombres']);

            $gravedad = match ($tipo) {
                'SUPLANTACION', 'INGRESO_REPETIDO', 'EXPULSION' => 'Grave',
                'INTENTO_NO_AUTORIZADO', 'MATERIAL_NO_PERMITIDO', 'DISPOSITIVO_NO_PERMITIDO' => 'Media',
                default => 'Leve',
            };
            $registradoPor = (string) ($fila['usuario'] ?: $fila['colaborador'] ?: 'Sistema');

            $resultado[] = [
                'hora' => substr((string) $fila['ocurrida_en'], 11, 5),
                'aula' => $fila['aula'] === null ? 'Sin aula' : (string) $fila['aula'],
                'codigo' => $codigo,
                'estudiante' => $nombre,
                'tipo' => $fila['origen'] === 'SISTEMA' && $causa !== null
                    ? 'Intento rechazado · '.$causa
                    : (self::TIPOS_INCIDENCIA[$tipo] ?? $tipo),
                'gravedad' => $gravedad,
                'detalle' => (string) $fila['detalle'],
                'registrado_por' => $fila['origen'] === 'SISTEMA'
                    ? 'Sistema'
                    : $registradoPor,
                'atencion' => $fila['atendida_en'] === null ? 'Sin atender' : 'Atendida',
                'ocurrida_en' => (string) $fila['ocurrida_en'],
                'rechazado' => $fila['origen'] === 'SISTEMA',
            ];
        }

        return $resultado;
    }

    private function inicio(string|int|float|bool|null $fecha, string|int|float|bool|null $hora): DateTimeImmutable
    {
        return new DateTimeImmutable(
            substr((string) $fecha, 0, 10).' '.substr((string) $hora, 0, 8),
            new DateTimeZone(date_default_timezone_get()),
        );
    }

    private function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
    }

    private function tipoTexto(string $tipo): string
    {
        return self::TIPOS_EXAMEN[$tipo] ?? $tipo;
    }

    private function medioTexto(string|int|float|bool|null $medio): string
    {
        return match ((string) $medio) {
            'qr' => 'Código QR',
            'codigo' => 'Código SIS',
            'documento' => 'C.I.',
            default => 'Sin dato',
        };
    }

    /**
     * @return array<string, int|float|string|bool|null>
     */
    private function campos(object $fila): array
    {
        $campos = [];

        foreach (get_object_vars($fila) as $nombre => $valor) {
            if (! is_string($nombre)) {
                throw new UnexpectedValueException('El resultado del reporte contiene un nombre de campo inválido.');
            }

            if (! is_int($valor) && ! is_float($valor) && ! is_string($valor) && ! is_bool($valor) && $valor !== null) {
                throw new UnexpectedValueException(sprintf('El campo "%s" del reporte no es escalar.', $nombre));
            }

            $campos[$nombre] = $valor;
        }

        return $campos;
    }

    private function entero(mixed $valor, string $campo): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor) && ctype_digit($valor)) {
            return (int) $valor;
        }

        throw new UnexpectedValueException(sprintf('El campo "%s" no contiene un entero válido.', $campo));
    }

    private function yaInicio(): \Closure
    {
        $ahora = $this->ahora();
        $hoy = $ahora->format('Y-m-d');
        $hora = $ahora->format('H:i:s');

        return static function (Builder $consulta) use ($hoy, $hora): void {
            $consulta->whereDate('examen.fecha', '<', $hoy)
                ->orWhere(static function (Builder $hoyMismo) use ($hoy, $hora): void {
                    $hoyMismo->whereDate('examen.fecha', $hoy)
                        ->where('examen.hora_inicio', '<=', $hora);
                });
        };
    }
}
