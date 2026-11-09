<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Infrastructure\Persistence;

use App\Modules\Monitoreo\Application\Contracts\SeguimientoGateway;
use App\Modules\Monitoreo\Application\DTOs\AulaEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\AulaSeguidaData;
use App\Modules\Monitoreo\Application\DTOs\CifrasEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\ConsultaAsistenciaData;
use App\Modules\Monitoreo\Application\DTOs\ExamenEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\ExamenSeguidoData;
use App\Modules\Monitoreo\Application\DTOs\FilaAsistenciaData;
use App\Modules\Monitoreo\Application\DTOs\PaginaAsistenciaData;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * El seguimiento no tiene tablas: lee las de examenes, aulas,
 * habilitaciones, ingresos e incidencias por nombre de tabla. Las pantallas
 * repiten estas consultas cada pocos segundos, asi que ninguna cuesta una
 * consulta por aula.
 *
 * La fecha y la hora de un examen se comparan con `now()` en la zona de la
 * aplicacion (`config('umss.zona_horaria')`), la misma en la que se guardan.
 */
final class EloquentSeguimientoGateway implements SeguimientoGateway
{
    private const TRAMO_MINUTOS = 5;

    private const TIPOS = [
        'PRIMER_PARCIAL' => 'Primer parcial',
        'SEGUNDO_PARCIAL' => 'Segundo parcial',
        'FINAL' => 'Examen final',
        'SEGUNDA_INSTANCIA' => 'Segunda instancia',
        'MESA' => 'Examen de mesa',
    ];

    private const MESES = [
        1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun',
        'jul', 'ago', 'sep', 'oct', 'nov', 'dic',
    ];

    private const SIN_TILDES = "translate(lower(%s), 'áéíóúüñ', 'aeiouun')";

    public function examen(int $examenId): ?ExamenSeguidoData
    {
        $fila = DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->where('examen.id', $examenId)
            ->select([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.duracion_minutos',
                'examen.finalizado_en',
                'asignatura.nombre as asignatura',
            ])
            ->first();

        if ($fila === null) {
            return null;
        }

        /** @var array<string, mixed> $columnas */
        $columnas = get_object_vars($fila);
        $ahora = $this->ahora();
        $fecha = $this->fecha($columnas['fecha'] ?? null);
        $hora = $this->texto($columnas['hora_inicio'] ?? null) ?? '00:00:00';
        $duracion = $this->entero($columnas['duracion_minutos'] ?? null);
        $finPrevisto = (new DateTimeImmutable("{$fecha} {$hora}", $ahora->getTimezone()))
            ->modify("+{$duracion} minutes");

        return new ExamenSeguidoData(
            id: $this->entero($columnas['id'] ?? null),
            asignatura: $this->texto($columnas['asignatura'] ?? null) ?? '',
            tipo: $this->tipo($columnas['tipo'] ?? null),
            fecha: $fecha,
            hora: substr($hora, 0, 5),
            abierto: $this->abierto(
                $ahora,
                $fecha,
                $hora,
                $this->texto($columnas['finalizado_en'] ?? null),
            ),
            esHoy: $fecha === $ahora->format('Y-m-d'),
            horaServidor: $ahora->format('Y-m-d\TH:i:sP'),
            fin: $finPrevisto->format('H:i'),
            excedido: ($columnas['finalizado_en'] ?? null) === null
                && $ahora > $finPrevisto
                && $fecha === $ahora->format('Y-m-d'),
        );
    }

    /**
     * @return list<ExamenEnCursoData>
     */
    public function enCurso(int $usuarioId): array
    {
        $ahora = $this->ahora();
        $hoy = $ahora->format('Y-m-d');

        $esDocente = <<<'SQL'
            (examen.creado_por = ? or exists (
                select 1
                from examen_grupo as incluido
                join grupos as grupo on grupo.id = incluido.grupo_id
                join docentes as docente on docente.id = grupo.docente_id
                where incluido.examen_id = examen.id and docente.user_id = ?
            ))
            SQL;

        $atiende = <<<'SQL'
            exists (
                select 1
                from examen_aula as asignada
                where asignada.examen_id = examen.id
                  and (asignada.encargado_id = ? or exists (
                      select 1
                      from examen_aula_apoyo as apoyo
                      where apoyo.examen_aula_id = asignada.id and apoyo.user_id = ?
                  ))
            )
            SQL;

        $filas = DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->whereDate('examen.fecha', '>=', $hoy)
            ->whereRaw("({$esDocente} or {$atiende})", [$usuarioId, $usuarioId, $usuarioId, $usuarioId])
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->select([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.duracion_minutos',
                'examen.finalizado_en',
                'asignatura.nombre as asignatura',
            ])
            ->selectRaw("{$esDocente} as es_docente", [$usuarioId, $usuarioId])
            ->get();

        $examenes = [];
        $proximo = null;

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $columnas */
            $columnas = get_object_vars($fila);
            $fecha = $this->fecha($columnas['fecha'] ?? null);

            if ($fecha !== $hoy) {
                $proximo ??= $fecha;

                if ($fecha !== $proximo) {
                    break;
                }
            }

            $examenes[$this->entero($columnas['id'] ?? null)] = $columnas + ['fecha_texto' => $fecha];
        }

        if ($examenes === []) {
            return [];
        }

        $aulas = $this->aulasPorExamen(array_keys($examenes), $usuarioId);
        $lista = [];

        foreach ($examenes as $id => $columnas) {
            $fecha = $this->texto($columnas['fecha_texto']) ?? '';
            $hora = $this->texto($columnas['hora_inicio'] ?? null) ?? '00:00:00';
            $asignatura = $this->texto($columnas['asignatura'] ?? null) ?? '';
            $docente = $this->booleano($columnas['es_docente'] ?? false);
            $suyas = [];

            foreach ($aulas[$id] ?? [] as $aula) {
                if ($docente || $aula['mia']) {
                    $suyas[] = new AulaSeguidaData($aula['id'], $aula['nombre']);
                }
            }

            $lista[] = new ExamenEnCursoData(
                examenId: $id,
                texto: implode(' · ', [
                    $this->diaCorto($fecha),
                    $this->tipo($columnas['tipo'] ?? null),
                    $asignatura,
                ]),
                asignatura: $asignatura,
                fecha: $fecha,
                hora: substr($hora, 0, 5),
                abierto: $this->abierto(
                    $ahora,
                    $fecha,
                    $hora,
                    $this->texto($columnas['finalizado_en'] ?? null),
                ),
                aulas: $suyas,
                tipo: $this->texto($columnas['tipo'] ?? null) ?? '',
                tipoTexto: $this->tipo($columnas['tipo'] ?? null),
            );
        }

        return $lista;
    }

    public function cifras(int $examenId): CifrasEnCursoData
    {
        $faltan = DB::table('habilitaciones as habilitacion')
            ->where('habilitacion.examen_id', $examenId)
            ->where('habilitacion.habilitado', true)
            ->whereNotExists(function (Builder $ingreso): void {
                $ingreso->selectRaw('1')
                    ->from('ingresos as ingreso')
                    ->whereColumn('ingreso.examen_id', 'habilitacion.examen_id')
                    ->whereColumn('ingreso.estudiante_id', 'habilitacion.estudiante_id');
            })
            ->count();

        return new CifrasEnCursoData(
            ingresaron: DB::table('ingresos')->where('examen_id', $examenId)->count(),
            faltan: $faltan,
            aulas: DB::table('examen_aula')->where('examen_id', $examenId)->count(),
            incidenciasSinAtender: $this->incidenciasSinAtender($examenId)->count(),
            ultimoIngreso: $this->instante(
                DB::table('ingresos')->where('examen_id', $examenId)->max('registrado_en')
            ),
            llegadas: $this->llegadas($examenId),
        );
    }

    /**
     * Cuantos entraron en cada tramo de cinco minutos, sin saltar los
     * tramos vacios: con eso el docente ve el ritmo de llegada.
     *
     * @return list<array{hora: string, ingresos: int}>
     */
    private function llegadas(int $examenId): array
    {
        $porTramo = [];

        foreach (DB::table('ingresos')->where('examen_id', $examenId)->pluck('registrado_en') as $instante) {
            $texto = $this->texto($instante);

            if ($texto === null || strlen($texto) < 16) {
                continue;
            }

            $minutos = (int) substr($texto, 11, 2) * 60 + (int) substr($texto, 14, 2);
            $tramo = intdiv($minutos, self::TRAMO_MINUTOS) * self::TRAMO_MINUTOS;
            $porTramo[$tramo] = ($porTramo[$tramo] ?? 0) + 1;
        }

        if ($porTramo === []) {
            return [];
        }

        $llegadas = [];

        for ($tramo = min(array_keys($porTramo)); $tramo <= max(array_keys($porTramo)); $tramo += self::TRAMO_MINUTOS) {
            $llegadas[] = [
                'hora' => sprintf('%02d:%02d', intdiv($tramo, 60), $tramo % 60),
                'ingresos' => $porTramo[$tramo] ?? 0,
            ];
        }

        return $llegadas;
    }

    /**
     * @return list<AulaEnCursoData>
     */
    public function aulas(int $examenId, int $usuarioId): array
    {
        $filas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->join('users as encargado', 'encargado.id', '=', 'asignada.encargado_id')
            ->where('asignada.examen_id', $examenId)
            ->orderBy('asignada.id')
            ->select([
                'asignada.id',
                'asignada.aula_id',
                'asignada.encargado_id',
                'asignada.codigo_acceso',
                'aula.nombre',
                'aula.edificio_id',
                'encargado.name as encargado',
            ])
            ->selectRaw(
                'exists (select 1 from docentes as docente where docente.user_id = asignada.encargado_id) as es_docente'
            )
            ->get();

        if ($filas->isEmpty()) {
            return [];
        }

        $asignados = $this->conteoPorAula(
            DB::table('habilitaciones')
                ->where('examen_id', $examenId)
                ->where('habilitado', true)
                ->whereNotNull('aula_id')
        );
        $ingresados = $this->conteoPorAula(
            DB::table('ingresos')->where('examen_id', $examenId)
        );
        $faltan = $this->conteoPorAula(
            DB::table('habilitaciones as habilitacion')
                ->where('habilitacion.examen_id', $examenId)
                ->where('habilitacion.habilitado', true)
                ->whereNotNull('habilitacion.aula_id')
                ->whereNotExists(function (Builder $ingreso): void {
                    $ingreso->selectRaw('1')
                        ->from('ingresos as ingreso')
                        ->whereColumn('ingreso.examen_id', 'habilitacion.examen_id')
                        ->whereColumn('ingreso.estudiante_id', 'habilitacion.estudiante_id');
                })
        );
        // Quienes entraron por la puerta de un aula que no era la suya, desde
        // cada lado: los que llegaron de otra y los que se fueron a otra.
        $cruzados = fn (): Builder => DB::table('ingresos as ingreso')
            ->join('habilitaciones as habilitacion', function (JoinClause $union): void {
                $union->on('habilitacion.examen_id', '=', 'ingreso.examen_id')
                    ->on('habilitacion.estudiante_id', '=', 'ingreso.estudiante_id');
            })
            ->where('ingreso.examen_id', $examenId)
            ->whereNotNull('habilitacion.aula_id')
            ->whereColumn('habilitacion.aula_id', '<>', 'ingreso.aula_id');
        $deOtra = $this->conteoPorAula($cruzados(), 'ingreso.aula_id');
        $enOtra = $this->conteoPorAula($cruzados(), 'habilitacion.aula_id');
        $incidencias = $this->conteoPorAula(
            $this->incidenciasSinAtender($examenId)->whereNotNull('aula_id')
        );
        $apoyo = $this->apoyoPorAsignacion($examenId);
        $compartidas = $this->compartidas($examenId);
        $ultimos = $this->ultimoIngresoPorAula($examenId);

        $aulas = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $columnas */
            $columnas = get_object_vars($fila);
            $aulaId = $this->entero($columnas['aula_id'] ?? null);
            $otros = $compartidas[$aulaId] ?? [];

            $aulas[] = new AulaEnCursoData(
                aulaId: $aulaId,
                nombre: $this->texto($columnas['nombre'] ?? null) ?? '',
                edificioId: $this->enteroOpcional($columnas['edificio_id'] ?? null),
                encargado: $this->texto($columnas['encargado'] ?? null) ?? '',
                encargadoEsDocente: $this->booleano($columnas['es_docente'] ?? false),
                apoyo: $apoyo[$this->entero($columnas['id'] ?? null)] ?? [],
                ingresados: $ingresados[$aulaId] ?? 0,
                asignados: $asignados[$aulaId] ?? 0,
                incidenciasSinAtender: $incidencias[$aulaId] ?? 0,
                compartida: $otros === [] ? null : implode(', ', $otros),
                usaCodigo: ($columnas['codigo_acceso'] ?? null) !== null,
                aMiCargo: $this->entero($columnas['encargado_id'] ?? null) === $usuarioId,
                ultimoIngreso: $ultimos[$aulaId] ?? null,
                faltan: $faltan[$aulaId] ?? 0,
                deOtra: $deOtra[$aulaId] ?? 0,
                enOtra: $enOtra[$aulaId] ?? 0,
            );
        }

        return $aulas;
    }

    public function aulaDelExamen(int $examenId, int $aulaId): ?AulaSeguidaData
    {
        $nombre = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->where('asignada.examen_id', $examenId)
            ->where('asignada.aula_id', $aulaId)
            ->value('aula.nombre');

        return $nombre === null
            ? null
            : new AulaSeguidaData($aulaId, $this->texto($nombre) ?? '');
    }

    public function asistencia(ConsultaAsistenciaData $consulta): PaginaAsistenciaData
    {
        $ingresaron = $consulta->lista === ConsultaAsistenciaData::INGRESARON;
        $lista = $ingresaron ? $this->ingresaron($consulta) : $this->faltan($consulta);

        if ($consulta->buscar !== null && trim($consulta->buscar) !== '') {
            $this->buscar($lista, $consulta->buscar);
        }

        $total = (clone $lista)->count();

        if ($ingresaron) {
            $lista
                ->orderByDesc('ingreso.registrado_en')
                ->orderByDesc('ingreso.id')
                ->select(['ingreso.registrado_en'])
                ->selectRaw(
                    "exists (select 1 from incidencias as expulsion where expulsion.ingreso_id = ingreso.id and expulsion.tipo = 'EXPULSION') as expulsado"
                );
        } else {
            $lista
                ->orderByRaw(sprintf(self::SIN_TILDES, 'estudiante.apellidos'))
                ->orderByRaw(sprintf(self::SIN_TILDES, 'estudiante.nombres'))
                ->orderBy('estudiante.id')
                ->select([]);
        }

        $filas = $lista
            ->addSelect([
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
            ])
            ->selectRaw(
                '(select min(grupo.codigo)
                    from inscripciones as inscripcion
                    join examen_grupo as incluido on incluido.grupo_id = inscripcion.grupo_id
                    join grupos as grupo on grupo.id = inscripcion.grupo_id
                    where inscripcion.estudiante_id = estudiante.id and inscripcion.baja_en is null
                      and incluido.examen_id = ?) as grupo',
                [$consulta->examenId]
            )
            ->forPage($consulta->pagina, $consulta->porPagina)
            ->get();

        $pagina = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $columnas */
            $columnas = get_object_vars($fila);
            $nombres = $this->texto($columnas['nombres'] ?? null) ?? '';
            $apellidos = $this->texto($columnas['apellidos'] ?? null) ?? '';
            $registrado = $this->texto($columnas['registrado_en'] ?? null);

            $pagina[] = new FilaAsistenciaData(
                codigo: $this->texto($columnas['codigo_universitario'] ?? null) ?? '',
                nombre: trim("{$apellidos} {$nombres}"),
                grupo: $this->texto($columnas['grupo'] ?? null),
                hora: $registrado === null ? null : substr($registrado, 11, 8),
                expulsado: $this->booleano($columnas['expulsado'] ?? false),
            );
        }

        return new PaginaAsistenciaData(
            ingresaron: $this->ingresaron($consulta)->count(),
            faltan: $this->faltan($consulta)->count(),
            asignados: DB::table('habilitaciones')
                ->where('examen_id', $consulta->examenId)
                ->where('aula_id', $consulta->aulaId)
                ->where('habilitado', true)
                ->count(),
            filas: $pagina,
            total: $total,
            enOtraAula: DB::table('habilitaciones as habilitacion')
                ->join('ingresos as ingreso', function (JoinClause $union): void {
                    $union->on('ingreso.examen_id', '=', 'habilitacion.examen_id')
                        ->on('ingreso.estudiante_id', '=', 'habilitacion.estudiante_id');
                })
                ->where('habilitacion.examen_id', $consulta->examenId)
                ->where('habilitacion.aula_id', $consulta->aulaId)
                ->where('habilitacion.habilitado', true)
                ->where('ingreso.aula_id', '<>', $consulta->aulaId)
                ->count(),
            deOtraAula: DB::table('ingresos as ingreso')
                ->leftJoin('habilitaciones as habilitacion', function (JoinClause $union): void {
                    $union->on('habilitacion.examen_id', '=', 'ingreso.examen_id')
                        ->on('habilitacion.estudiante_id', '=', 'ingreso.estudiante_id');
                })
                ->where('ingreso.examen_id', $consulta->examenId)
                ->where('ingreso.aula_id', $consulta->aulaId)
                ->where(static function (Builder $otra) use ($consulta): void {
                    $otra->whereNull('habilitacion.aula_id')
                        ->orWhere('habilitacion.aula_id', '<>', $consulta->aulaId);
                })
                ->count(),
        );
    }

    /**
     * Habilitados con esa aula que todavia no tienen ingreso en el examen.
     */
    private function faltan(ConsultaAsistenciaData $consulta): Builder
    {
        return DB::table('habilitaciones as habilitacion')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'habilitacion.estudiante_id')
            ->where('habilitacion.examen_id', $consulta->examenId)
            ->where('habilitacion.aula_id', $consulta->aulaId)
            ->where('habilitacion.habilitado', true)
            ->whereNotExists(function (Builder $ingreso): void {
                $ingreso->selectRaw('1')
                    ->from('ingresos as ingreso')
                    ->whereColumn('ingreso.examen_id', 'habilitacion.examen_id')
                    ->whereColumn('ingreso.estudiante_id', 'habilitacion.estudiante_id');
            });
    }

    /**
     * Los ingresos del examen por la puerta de esa aula.
     */
    private function ingresaron(ConsultaAsistenciaData $consulta): Builder
    {
        return DB::table('ingresos as ingreso')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'ingreso.estudiante_id')
            ->where('ingreso.examen_id', $consulta->examenId)
            ->where('ingreso.aula_id', $consulta->aulaId);
    }

    /**
     * Por nombre, codigo universitario o documento de identidad, sin
     * distinguir mayusculas ni tildes.
     */
    private function buscar(Builder $lista, string $texto): void
    {
        $limpio = strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $patron = '%'.addcslashes($limpio, '\\%_').'%';

        $lista->where(function (Builder $coincide) use ($patron): void {
            $coincide
                ->whereRaw(
                    sprintf(self::SIN_TILDES, "estudiante.apellidos || ' ' || estudiante.nombres").' like ?',
                    [$patron]
                )
                ->orWhereRaw(
                    sprintf(self::SIN_TILDES, "estudiante.nombres || ' ' || estudiante.apellidos").' like ?',
                    [$patron]
                )
                ->orWhereRaw('lower(estudiante.codigo_universitario) like ?', [$patron])
                ->orWhereRaw('lower(estudiante.documento_identidad) like ?', [$patron]);
        });
    }

    /**
     * Las que reporto una persona y los ingresos repetidos: el resto de
     * los rechazos de la puerta no se atienden.
     */
    private function incidenciasSinAtender(int $examenId): Builder
    {
        return DB::table('incidencias')
            ->where('examen_id', $examenId)
            ->where(static function (Builder $donde): void {
                $donde->where('origen', 'PERSONA')->orWhere('tipo', 'INGRESO_REPETIDO');
            })
            ->whereNull('atendida_en');
    }

    /**
     * @return array<int, int>
     */
    private function conteoPorAula(Builder $consulta, string $columna = 'aula_id'): array
    {
        $filas = $consulta
            ->groupBy($columna)
            ->select([$columna.' as aula_id'])
            ->selectRaw('count(*) as cantidad')
            ->get();

        $conteo = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $columnas */
            $columnas = get_object_vars($fila);

            $conteo[$this->entero($columnas['aula_id'] ?? null)] = $this->entero($columnas['cantidad'] ?? null);
        }

        return $conteo;
    }

    /**
     * El instante del ultimo ingreso por la puerta de cada aula.
     *
     * @return array<int, string>
     */
    private function ultimoIngresoPorAula(int $examenId): array
    {
        $filas = DB::table('ingresos')
            ->where('examen_id', $examenId)
            ->groupBy('aula_id')
            ->select(['aula_id'])
            ->selectRaw('max(registrado_en) as ultimo')
            ->get();

        $ultimos = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $columnas */
            $columnas = get_object_vars($fila);
            $ultimo = $this->instante($columnas['ultimo'] ?? null);

            if ($ultimo !== null) {
                $ultimos[$this->entero($columnas['aula_id'] ?? null)] = $ultimo;
            }
        }

        return $ultimos;
    }

    /**
     * @return array<int, list<string>>
     */
    private function apoyoPorAsignacion(int $examenId): array
    {
        $filas = DB::table('examen_aula_apoyo as apoyo')
            ->join('examen_aula as asignada', 'asignada.id', '=', 'apoyo.examen_aula_id')
            ->join('users as cuenta', 'cuenta.id', '=', 'apoyo.user_id')
            ->where('asignada.examen_id', $examenId)
            ->orderBy('cuenta.name')
            ->select(['apoyo.examen_aula_id', 'cuenta.name'])
            ->get();

        $apoyo = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $columnas */
            $columnas = get_object_vars($fila);

            $apoyo[$this->entero($columnas['examen_aula_id'] ?? null)][] = $this->texto($columnas['name'] ?? null) ?? '';
        }

        return $apoyo;
    }

    /**
     * Por aula, los otros examenes del mismo dia cuyo horario se solapa
     * con el de este (`inicio < fin_otro` e `inicio_otro < fin`).
     *
     * @return array<int, list<string>>
     */
    private function compartidas(int $examenId): array
    {
        $filas = DB::table('examenes as examen')
            ->join('examen_aula as propia', 'propia.examen_id', '=', 'examen.id')
            ->join('examen_aula as ajena', 'ajena.aula_id', '=', 'propia.aula_id')
            ->join('examenes as otro', 'otro.id', '=', 'ajena.examen_id')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'otro.asignatura_id')
            ->where('examen.id', $examenId)
            ->whereColumn('otro.id', '<>', 'examen.id')
            ->whereColumn('otro.fecha', 'examen.fecha')
            ->whereRaw("examen.hora_inicio < otro.hora_inicio + otro.duracion_minutos * interval '1 minute'")
            ->whereRaw("otro.hora_inicio < examen.hora_inicio + examen.duracion_minutos * interval '1 minute'")
            ->orderBy('otro.hora_inicio')
            ->orderBy('otro.id')
            ->select(['propia.aula_id', 'otro.tipo', 'asignatura.nombre'])
            ->get();

        $compartidas = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $columnas */
            $columnas = get_object_vars($fila);

            $compartidas[$this->entero($columnas['aula_id'] ?? null)][] = implode(' · ', [
                $this->texto($columnas['nombre'] ?? null) ?? '',
                $this->tipo($columnas['tipo'] ?? null),
            ]);
        }

        return $compartidas;
    }

    /**
     * @param  list<int>  $examenIds
     * @return array<int, list<array{id: int, nombre: string, mia: bool}>>
     */
    private function aulasPorExamen(array $examenIds, int $usuarioId): array
    {
        $filas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->whereIn('asignada.examen_id', $examenIds)
            ->orderBy('asignada.id')
            ->select(['asignada.examen_id', 'asignada.aula_id', 'aula.nombre'])
            ->selectRaw(
                '(asignada.encargado_id = ? or exists (
                    select 1 from examen_aula_apoyo as apoyo
                    where apoyo.examen_aula_id = asignada.id and apoyo.user_id = ?
                )) as mia',
                [$usuarioId, $usuarioId]
            )
            ->get();

        $aulas = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $columnas */
            $columnas = get_object_vars($fila);

            $aulas[$this->entero($columnas['examen_id'] ?? null)][] = [
                'id' => $this->entero($columnas['aula_id'] ?? null),
                'nombre' => $this->texto($columnas['nombre'] ?? null) ?? '',
                'mia' => $this->booleano($columnas['mia'] ?? false),
            ];
        }

        return $aulas;
    }

    /**
     * Abierto desde los minutos de apertura antes del inicio hasta que el
     * docente lo finaliza (puede pasarse de su duracion); sin finalizar, hasta
     * que termina el dia del examen.
     */
    private function abierto(DateTimeImmutable $ahora, string $fecha, string $hora, ?string $finalizado): bool
    {
        $zona = $ahora->getTimezone();
        $inicio = new DateTimeImmutable("{$fecha} {$hora}", $zona);
        $antes = config('umss.minutos_apertura', 60);
        $antes = is_numeric($antes) ? (int) $antes : 60;
        $cierre = $finalizado !== null
            ? new DateTimeImmutable($finalizado, $zona)
            : (new DateTimeImmutable("{$fecha} 00:00:00", $zona))->modify('+1 day');

        return $ahora >= $inicio->modify("-{$antes} minutes") && $ahora < $cierre;
    }

    private function ahora(): DateTimeImmutable
    {
        return now()->toDateTimeImmutable();
    }

    /**
     * Pasa un instante de la base (`AAAA-MM-DD HH:MM:SS`, en la zona de la
     * aplicacion) a ISO 8601 con zona.
     */
    private function instante(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        if ($texto === null) {
            return null;
        }

        return (new DateTimeImmutable(substr($texto, 0, 19), $this->ahora()->getTimezone()))
            ->format('Y-m-d\TH:i:sP');
    }

    private function diaCorto(string $fecha): string
    {
        [, $mes, $dia] = array_map('intval', explode('-', $fecha) + [0, 1, 1]);

        return $dia.' '.(self::MESES[$mes] ?? '');
    }

    private function tipo(mixed $valor): string
    {
        $clave = $this->texto($valor) ?? '';

        return self::TIPOS[$clave] ?? $clave;
    }

    /**
     * La fecha llega como `AAAA-MM-DD`, con o sin hora detras.
     */
    private function fecha(mixed $valor): string
    {
        return substr($this->texto($valor) ?? '', 0, 10);
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_string($valor)) {
            return $valor;
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        throw new LogicException('La columna no contiene un texto válido.');
    }

    private function entero(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor) && preg_match('/^-?\d+$/', $valor) === 1) {
            return (int) $valor;
        }

        throw new LogicException('La columna no contiene un entero válido.');
    }

    private function enteroOpcional(mixed $valor): ?int
    {
        return $valor === null ? null : $this->entero($valor);
    }

    private function booleano(mixed $valor): bool
    {
        return $valor === true || $valor === 1 || $valor === 't' || $valor === 'true' || $valor === '1';
    }
}
