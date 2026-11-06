<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Persistence;

use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\DTOs\AlcanceReporte;
use App\Modules\Reportes\Application\DTOs\ExamenReporteData;
use App\Modules\Reportes\Application\DTOs\FilaAsistencia;
use App\Modules\Reportes\Application\DTOs\FilaAusente;
use App\Modules\Reportes\Application\DTOs\FilaEstudianteGrupo;
use App\Modules\Reportes\Application\DTOs\FilaExamenReporte;
use App\Modules\Reportes\Application\DTOs\FilaIncidencia;
use App\Modules\Reportes\Application\DTOs\FilaIntento;
use App\Modules\Reportes\Application\DTOs\FilaMiExamen;
use App\Modules\Reportes\Application\DTOs\FilaRegistrados;
use App\Modules\Reportes\Application\DTOs\FilaResumenEstudiante;
use App\Modules\Reportes\Application\DTOs\GrupoReporteData;
use App\Modules\Reportes\Application\DTOs\ReporteHorarios;
use App\Modules\Reportes\Domain\Rules\FechaLegible;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Los reportes solo leen, y leen tablas de otros modulos: por eso todo
 * sale de `DB::table`, sin modelos. Las horas se muestran como estan
 * guardadas (la zona de la aplicacion), igual que la fecha y la hora del
 * examen.
 */
final class EloquentReporteGateway implements ReporteGateway
{
    /**
     * Ancho de cada tramo del reporte de horarios, en minutos.
     */
    private const TRAMO = 15;

    /**
     * La facultad de un examen es la de su primer grupo: un examen figura
     * en una sola facultad, para que los conteos sumen el total.
     */
    private const FACULTAD_DEL_EXAMEN = '(select grupo_f.facultad_id from examen_grupo as eg_f'
        .' join grupos as grupo_f on grupo_f.id = eg_f.grupo_id'
        .' where eg_f.examen_id = examen.id order by eg_f.id limit 1)';

    /**
     * Quien registro el examen: su nombre de docente o, si la cuenta no es
     * de un docente, el de la cuenta.
     */
    private const DOCENTE = 'coalesce(docente.nombre_completo, creador.nombre)';

    /**
     * El grupo del estudiante entre los del examen.
     */
    private const GRUPO_DEL_ESTUDIANTE = '(select grupo_e.codigo from inscripciones as inscripcion_e'
        .' join examen_grupo as eg_e on eg_e.grupo_id = inscripcion_e.grupo_id'
        .' join grupos as grupo_e on grupo_e.id = inscripcion_e.grupo_id'
        .' where inscripcion_e.estudiante_id = estudiante.id'
        .' and eg_e.examen_id = %s'
        .' order by grupo_e.id limit 1) as grupo';

    /**
     * La ventana de un examen (alias `%1$s`): desde que abre la puerta
     * (un parametro: los minutos de apertura) hasta que el docente lo
     * finaliza, que puede pasar de su duracion prevista; sin finalizar,
     * hasta el fin de su dia.
     */
    private const EN_LA_VENTANA = "incidencia.ocurrida_en >= (%1\$s.fecha + %1\$s.hora_inicio) - (?::int * interval '1 minute')"
        ." and incidencia.ocurrida_en <= coalesce(%1\$s.finalizado_en, %1\$s.fecha + interval '1 day')";

    /**
     * Lo asentado en el examen `examen`: lo que lleva su `examen_id` y los
     * rechazos de la puerta sin examen que ocurrieron en una de sus aulas
     * dentro de su ventana. Lleva un parametro: los minutos de apertura.
     */
    private const INCIDENCIA_DEL_EXAMEN = '(incidencia.examen_id = examen.id or ('
        ."incidencia.examen_id is null and incidencia.origen = 'SISTEMA'"
        .' and exists (select 1 from examen_aula as puerta'
        .' where puerta.examen_id = examen.id and puerta.aula_id = incidencia.aula_id)'
        .' and %s))';

    /**
     * @var array<string, string>
     */
    private const TIPOS = [
        'PRIMER_PARCIAL' => 'Primer parcial',
        'SEGUNDO_PARCIAL' => 'Segundo parcial',
        'FINAL' => 'Examen final',
        'SEGUNDA_INSTANCIA' => 'Segunda instancia',
        'MESA' => 'Examen de mesa',
    ];

    /**
     * Comprueba el volumen en PostgreSQL antes de ejecutar las consultas
     * que materializan arreglos y objetos del reporte.
     *
     * En reportes consolidados se contabilizan registros de origen:
     * el presupuesto es conservador para evitar grandes consultas intermedias.
     */
    public function validarVolumenExportacion(
        string $tipo,
        AlcanceReporte $alcance,
        int $limite
    ): void {
        if ($limite < 1) {
            throw new \InvalidArgumentException('El límite debe ser positivo.');
        }

        $examenId = $alcance->examenId;

        $volumen = match ($tipo) {
            'horarios' => $this->contarIngresos($alcance),

            'registrados' => $this->contarExamenes($alcance)
                + DB::table('examen_aula')
                    ->whereIn('examen_id', $this->idsDe($alcance))->count()
                + DB::table('habilitaciones')
                    ->whereIn('examen_id', $this->idsDe($alcance))->count()
                + $this->contarIngresos($alcance),

            'intentos' => $this->contarIntentos($alcance),

            'estudiantes' => $this->contarEstudiantes($alcance)
                + DB::table('inscripciones as inscripcion')
                    ->join('examen_grupo as eg', 'eg.grupo_id', '=', 'inscripcion.grupo_id')
                    ->whereIn('eg.examen_id', $this->idsDe($alcance))
                    ->count()
                + DB::table('habilitaciones')
                    ->whereIn('examen_id', $this->idsDe($alcance))->count()
                + $this->contarIngresos($alcance)
                + $this->incidenciasDe($alcance)->count(),

            'incidencias' => $examenId === null
                ? 0
                : DB::table('incidencias as incidencia')
                    ->crossJoin('examenes as examen')
                    ->where('examen.id', $examenId)
                    ->whereRaw(
                        $this->incidenciaDelExamen(),
                        [$this->apertura()]
                    )->count(),

            'asistencia' => $examenId === null
                ? 0
                : DB::table('ingresos')
                    ->where('examen_id', $examenId)->count(),

            'ausentes' => $examenId === null
                ? 0
                : DB::table('habilitaciones as habilitacion')
                    ->where('habilitacion.examen_id', $examenId)
                    ->where('habilitacion.habilitado', true)
                    ->whereNotExists(function (Builder $existe): void {
                        $existe->selectRaw('1')
                            ->from('ingresos as ingreso')
                            ->whereColumn(
                                'ingreso.estudiante_id',
                                'habilitacion.estudiante_id'
                            )
                            ->whereColumn(
                                'ingreso.examen_id',
                                'habilitacion.examen_id'
                            );
                    })->count(),

            default => throw new \InvalidArgumentException(
                'Tipo de reporte no válido.'
            ),
        };

        if ($volumen > $limite) {
            throw new DomainException(
                'El reporte supera el límite de volumen para exportación. '
                .'Reduzca el alcance mediante los filtros disponibles.'
            );
        }
    }

    public function contarExamenes(AlcanceReporte $alcance): int
    {
        return $this->examenesDe($alcance)->count();
    }

    /**
     * @return list<FilaExamenReporte>
     */
    public function examenes(AlcanceReporte $alcance, int $pagina, int $porPagina): array
    {
        $filas = $this->examenesDe($alcance)
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->forPage($pagina, $porPagina)
            ->select([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'asignatura.nombre as asignatura',
            ])
            ->selectRaw(self::DOCENTE.' as docente')
            ->selectRaw(
                '(select facultad.sigla from facultades as facultad'
                .' where facultad.id = '.self::FACULTAD_DEL_EXAMEN.') as facultad'
            )
            ->selectRaw(
                '(select count(*) from examen_aula as ea where ea.examen_id = examen.id) as aulas'
            )
            ->selectRaw(
                '(select count(*) from ingresos as ingreso where ingreso.examen_id = examen.id) as ingresados'
            )
            ->selectRaw(
                '(select count(*) from habilitaciones as habilitacion'
                .' where habilitacion.examen_id = examen.id and habilitacion.habilitado) as habilitados'
            )
            ->get();

        $examenes = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $examenes[] = new FilaExamenReporte(
                id: $this->entero($columnas['id'] ?? null),
                asignatura: $this->texto($columnas['asignatura'] ?? null) ?? '',
                tipo: $this->tipo($columnas['tipo'] ?? null),
                docente: $this->texto($columnas['docente'] ?? null) ?? '',
                facultad: $this->texto($columnas['facultad'] ?? null),
                fecha: $this->fecha($columnas['fecha'] ?? null),
                hora: $this->hora($columnas['hora_inicio'] ?? null),
                aulas: $this->entero($columnas['aulas'] ?? 0),
                ingresados: $this->entero($columnas['ingresados'] ?? 0),
                habilitados: $this->entero($columnas['habilitados'] ?? 0),
            );
        }

        return $examenes;
    }

    /**
     * @return array<string, int>
     */
    public function examenesPorFacultad(AlcanceReporte $alcance): array
    {
        $porExamen = $this->examenesDe($alcance)
            ->selectRaw(self::FACULTAD_DEL_EXAMEN.' as facultad_id');

        $cantidades = [];

        $filas = DB::query()
            ->fromSub($porExamen, 'por_examen')
            ->groupBy('por_examen.facultad_id')
            ->selectRaw('por_examen.facultad_id as facultad_id')
            ->selectRaw('count(*) as cantidad')
            ->get();

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $facultadId = $columnas['facultad_id'] ?? null;

            if ($facultadId !== null) {
                $cantidades[$this->entero($facultadId)] = $this->entero($columnas['cantidad'] ?? 0);
            }
        }

        $conteos = [];

        foreach (DB::table('facultades')->orderBy('orden')->orderBy('id')->get(['id', 'sigla']) as $facultad) {
            $columnas = $this->columnas($facultad);
            $sigla = $this->texto($columnas['sigla'] ?? null) ?? '';

            $conteos[$sigla] = $cantidades[$this->entero($columnas['id'] ?? null)] ?? 0;
        }

        return $conteos;
    }

    public function contarIngresos(AlcanceReporte $alcance): int
    {
        return DB::table('ingresos')
            ->whereIn('examen_id', $this->idsDe($alcance))
            ->count();
    }

    public function contarIntentos(AlcanceReporte $alcance): int
    {
        return $this->intentosDe($alcance)->count();
    }

    public function horarios(AlcanceReporte $alcance): ReporteHorarios
    {
        $horas = DB::table('ingresos')
            ->whereIn('examen_id', $this->idsDe($alcance))
            ->orderBy('registrado_en')
            ->orderBy('id')
            ->pluck('registrado_en');

        $marcas = [];

        foreach ($horas as $hora) {
            $texto = $this->instante($hora);

            if ($texto !== '') {
                $marcas[] = $texto;
            }
        }

        if ($marcas === []) {
            return new ReporteHorarios([], null, null, 0);
        }

        $conteo = [];

        foreach ($marcas as $marca) {
            $tramo = $this->tramo($marca);
            $conteo[$tramo] = ($conteo[$tramo] ?? 0) + 1;
        }

        ksort($conteo);

        $tramos = [];

        foreach ($conteo as $tramo => $cantidad) {
            $tramos[] = ['tramo' => (string) $tramo, 'cantidad' => $cantidad];
        }

        return new ReporteHorarios(
            tramos: $tramos,
            primerIngreso: $marcas[0],
            ultimoIngreso: $marcas[count($marcas) - 1],
            total: count($marcas),
        );
    }

    /**
     * @return list<FilaRegistrados>
     */
    public function registrados(AlcanceReporte $alcance): array
    {
        $examenes = $this->examenesDe($alcance)
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->select([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'asignatura.nombre as asignatura',
            ])
            ->selectRaw(self::DOCENTE.' as docente')
            ->selectRaw(
                '(select facultad.sigla from facultades as facultad'
                .' where facultad.id = '.self::FACULTAD_DEL_EXAMEN.') as facultad'
            )
            ->get();

        // Clave 0: sin aula (habilitados todavia sin repartir).
        $habilitados = $this->porExamenYAula(
            DB::table('habilitaciones')->where('habilitado', true),
            $alcance,
        );
        $registrados = $this->porExamenYAula(DB::table('ingresos'), $alcance);
        $asignadas = $this->porExamenYAula(DB::table('examen_aula'), $alcance);

        $aulaIds = [];

        foreach ([$habilitados, $registrados, $asignadas] as $mapa) {
            foreach ($mapa as $porAula) {
                foreach (array_keys($porAula) as $aulaId) {
                    $aulaIds[$aulaId] = true;
                }
            }
        }

        $nombres = [];

        foreach (DB::table('aulas')->whereIn('id', array_keys($aulaIds))->get(['id', 'nombre']) as $aula) {
            $columnas = $this->columnas($aula);
            $nombres[$this->entero($columnas['id'] ?? null)] = $this->texto($columnas['nombre'] ?? null) ?? '';
        }

        $filas = [];

        foreach ($examenes as $examen) {
            $columnas = $this->columnas($examen);
            $examenId = $this->entero($columnas['id'] ?? null);

            $aulas = array_keys(
                ($asignadas[$examenId] ?? []) + ($habilitados[$examenId] ?? []) + ($registrados[$examenId] ?? [])
            );

            // Sin aulas ni habilitados el examen igual figura, en cero.
            if ($aulas === []) {
                $aulas = [0];
            }

            // Por nombre de aula; «sin aula» al final.
            usort($aulas, static fn (int $a, int $b): int => [$a === 0, $nombres[$a] ?? '']
                <=> [$b === 0, $nombres[$b] ?? '']);

            foreach ($aulas as $aulaId) {
                $filas[] = new FilaRegistrados(
                    examenId: $examenId,
                    fecha: $this->fecha($columnas['fecha'] ?? null),
                    examen: sprintf(
                        '%s · %s',
                        $this->texto($columnas['asignatura'] ?? null) ?? '',
                        $this->tipo($columnas['tipo'] ?? null),
                    ),
                    facultad: $this->texto($columnas['facultad'] ?? null),
                    docente: $this->texto($columnas['docente'] ?? null) ?? '',
                    aula: $aulaId === 0 ? null : ($nombres[$aulaId] ?? null),
                    habilitados: $habilitados[$examenId][$aulaId] ?? 0,
                    registrados: $registrados[$examenId][$aulaId] ?? 0,
                );
            }
        }

        return $filas;
    }

    /**
     * @return list<FilaIntento>
     */
    public function intentos(AlcanceReporte $alcance): array
    {
        $filas = $this->intentosDe($alcance)
            ->leftJoin('estudiantes as estudiante', 'estudiante.id', '=', 'incidencia.estudiante_id')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'incidencia.aula_id')
            ->orderBy('incidencia.ocurrida_en')
            ->orderBy('incidencia.id')
            // A que examen intento ingresar: el que anoto la puerta o, si
            // no pudo atribuirlo, los que usaban esa aula a esa hora.
            ->selectRaw(
                'coalesce('
                .'(select '.$this->examenComoTexto('intentado', 'materia_i')
                .' from examenes as intentado'
                .' join asignaturas as materia_i on materia_i.id = intentado.asignatura_id'
                .' where intentado.id = incidencia.examen_id), '
                .'(select string_agg('.$this->examenComoTexto('abierto', 'materia_a').", '; '"
                .' order by abierto.fecha, abierto.hora_inicio, abierto.id)'
                .' from examenes as abierto'
                .' join asignaturas as materia_a on materia_a.id = abierto.asignatura_id'
                .' where incidencia.examen_id is null'
                .' and exists (select 1 from examen_aula as puerta_a'
                .' where puerta_a.examen_id = abierto.id and puerta_a.aula_id = incidencia.aula_id)'
                .' and '.sprintf(self::EN_LA_VENTANA, 'abierto').')'
                .') as intentado',
                [$this->apertura()],
            )
            ->addSelect([
                'incidencia.estudiante_id',
                'incidencia.estudiante_texto',
                'incidencia.causa',
                'incidencia.ocurrida_en',
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
                'aula.nombre as aula',
            ])
            ->get();

        $intentos = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $intentos[] = new FilaIntento(
                ocurridaEn: $this->instante($columnas['ocurrida_en'] ?? null),
                codigoUniversitario: $this->texto($columnas['codigo_universitario'] ?? null),
                // Sin estudiante identificado queda lo que se tecleo o leyo.
                estudiante: ($columnas['estudiante_id'] ?? null) === null
                    ? $this->texto($columnas['estudiante_texto'] ?? null)
                    : $this->nombre($columnas),
                aula: $this->texto($columnas['aula'] ?? null),
                examenIntentado: $this->texto($columnas['intentado'] ?? null),
                examenCorrespondiente: null,
                causa: $this->texto($columnas['causa'] ?? null),
            );
        }

        return $intentos;
    }

    public function contarEstudiantes(AlcanceReporte $alcance): int
    {
        return DB::query()
            ->fromSub($this->implicadosDe($alcance), 'implicado')
            ->distinct()
            ->count('implicado.estudiante_id');
    }

    /**
     * @return list<FilaResumenEstudiante>
     */
    public function resumenPorEstudiante(AlcanceReporte $alcance): array
    {
        // Examenes y materias de cada estudiante.
        $porExamen = DB::query()
            ->fromSub($this->participantesDe($alcance), 'participante')
            ->join('examenes as examen', 'examen.id', '=', 'participante.examen_id')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->groupBy('participante.estudiante_id')
            ->selectRaw('participante.estudiante_id as estudiante_id')
            ->selectRaw('count(distinct participante.examen_id) as examenes')
            ->selectRaw('string_agg(distinct asignatura.nombre, chr(31)) as materias')
            ->get();

        $examenes = [];
        $materias = [];

        foreach ($porExamen as $fila) {
            $columnas = $this->columnas($fila);
            $estudianteId = $this->entero($columnas['estudiante_id'] ?? null);

            $examenes[$estudianteId] = $this->entero($columnas['examenes'] ?? 0);
            $materias[$estudianteId] = $this->enOrden($this->texto($columnas['materias'] ?? null) ?? '');
        }

        $ingresos = [];

        $porIngreso = DB::table('ingresos')
            ->whereIn('examen_id', $this->idsDe($alcance))
            ->groupBy('estudiante_id')
            ->selectRaw('estudiante_id, count(*) as cantidad')
            ->get();

        foreach ($porIngreso as $fila) {
            $columnas = $this->columnas($fila);
            $ingresos[$this->entero($columnas['estudiante_id'] ?? null)] = $this->entero($columnas['cantidad'] ?? 0);
        }

        $asentado = [];

        $porIncidencia = $this->incidenciasDe($alcance)
            ->whereNotNull('incidencia.estudiante_id')
            ->groupBy('incidencia.estudiante_id')
            ->selectRaw('incidencia.estudiante_id as estudiante_id')
            ->selectRaw("count(*) filter (where incidencia.origen = 'SISTEMA' and incidencia.tipo = 'INTENTO_NO_AUTORIZADO') as intentos")
            ->selectRaw(
                "count(*) filter (where incidencia.origen <> 'SISTEMA' and incidencia.tipo = 'EXPULSION') as expulsiones"
            )
            ->selectRaw(
                "count(*) filter (where incidencia.origen <> 'SISTEMA' and incidencia.tipo <> 'EXPULSION') as incidencias"
            )
            ->get();

        foreach ($porIncidencia as $fila) {
            $columnas = $this->columnas($fila);

            $asentado[$this->entero($columnas['estudiante_id'] ?? null)] = [
                $this->entero($columnas['intentos'] ?? 0),
                $this->entero($columnas['expulsiones'] ?? 0),
                $this->entero($columnas['incidencias'] ?? 0),
            ];
        }

        $filas = DB::table('estudiantes as estudiante')
            ->leftJoin('carreras as carrera', 'carrera.id', '=', 'estudiante.carrera_id')
            ->whereIn(
                'estudiante.id',
                DB::query()
                    ->fromSub($this->implicadosDe($alcance), 'implicado')
                    ->select('implicado.estudiante_id'),
            )
            ->orderByRaw("translate(lower(estudiante.apellidos), 'áéíóúüñ', 'aeiouun')")
            ->orderByRaw("translate(lower(estudiante.nombres), 'áéíóúüñ', 'aeiouun')")
            ->orderBy('estudiante.id')
            ->select([
                'estudiante.id',
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
                'carrera.nombre as carrera',
            ])
            ->get();

        $resumen = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $estudianteId = $this->entero($columnas['id'] ?? null);
            [$intentos, $expulsiones, $incidencias] = $asentado[$estudianteId] ?? [0, 0, 0];

            $resumen[] = new FilaResumenEstudiante(
                codigoUniversitario: $this->texto($columnas['codigo_universitario'] ?? null) ?? '',
                nombre: $this->nombre($columnas),
                carrera: $this->texto($columnas['carrera'] ?? null),
                materias: $materias[$estudianteId] ?? '',
                examenes: $examenes[$estudianteId] ?? 0,
                ingresos: $ingresos[$estudianteId] ?? 0,
                intentos: $intentos,
                expulsiones: $expulsiones,
                incidencias: $incidencias,
            );
        }

        return $resumen;
    }

    /**
     * @return list<FilaIncidencia>
     */
    public function incidenciasDeExamen(int $examenId): array
    {
        $filas = DB::table('incidencias as incidencia')
            ->crossJoin('examenes as examen')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'incidencia.aula_id')
            ->leftJoin('estudiantes as estudiante', 'estudiante.id', '=', 'incidencia.estudiante_id')
            ->leftJoin('usuarios as autor', 'autor.id', '=', 'incidencia.reportada_por')
            ->leftJoin('docentes as docente_autor', 'docente_autor.user_id', '=', 'autor.id')
            ->leftJoin('accesos_codigo_aula as acceso', 'acceso.id', '=', 'incidencia.acceso_codigo_aula_id')
            ->where('examen.id', $examenId)
            ->whereRaw($this->incidenciaDelExamen(), [$this->apertura()])
            ->orderBy('incidencia.ocurrida_en')
            ->orderBy('incidencia.id')
            ->select([
                'incidencia.estudiante_id',
                'incidencia.estudiante_texto',
                'incidencia.tipo',
                'incidencia.origen',
                'incidencia.causa',
                'incidencia.detalle',
                'incidencia.ocurrida_en',
                'aula.nombre as aula',
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
                'acceso.nombre_colaborador as acceso',
            ])
            // Como en los demas reportes: el nombre de docente o el de la cuenta.
            ->selectRaw('coalesce(docente_autor.nombre_completo, autor.nombre) as autor')
            ->get();

        $incidencias = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $autor = $this->texto($columnas['autor'] ?? null);

            $incidencias[] = new FilaIncidencia(
                hora: substr($this->instante($columnas['ocurrida_en'] ?? null), 11),
                aula: $this->texto($columnas['aula'] ?? null),
                codigoUniversitario: $this->texto($columnas['codigo_universitario'] ?? null),
                // Sin estudiante identificado queda lo que se tecleo o leyo.
                estudiante: ($columnas['estudiante_id'] ?? null) === null
                    ? $this->texto($columnas['estudiante_texto'] ?? null)
                    : $this->nombre($columnas),
                tipo: $this->texto($columnas['tipo'] ?? null) ?? '',
                origen: $this->texto($columnas['origen'] ?? null) ?? '',
                causa: $this->texto($columnas['causa'] ?? null),
                detalle: $this->texto($columnas['detalle'] ?? null) ?? '',
                registradoPor: $autor ?? $this->texto($columnas['acceso'] ?? null),
                conCodigoDeAula: $autor === null && ($columnas['acceso'] ?? null) !== null,
            );
        }

        return $incidencias;
    }

    /**
     * @return list<FilaAsistencia>
     */
    public function asistencia(int $examenId): array
    {
        $filas = DB::table('ingresos as ingreso')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'ingreso.estudiante_id')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'ingreso.aula_id')
            ->where('ingreso.examen_id', $examenId)
            ->orderBy('ingreso.registrado_en')
            ->orderBy('ingreso.id')
            ->select([
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
                'aula.nombre as aula',
                'ingreso.registrado_en',
                'ingreso.medio_identificacion',
                'ingreso.es_excepcion',
            ])
            ->selectRaw(sprintf(self::GRUPO_DEL_ESTUDIANTE, 'ingreso.examen_id'))
            ->selectRaw(
                'exists (select 1 from incidencias as expulsion where expulsion.ingreso_id = ingreso.id'
                ." and expulsion.tipo = 'EXPULSION') as expulsado"
            )
            ->get();

        $asistencia = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $asistencia[] = new FilaAsistencia(
                codigoUniversitario: $this->texto($columnas['codigo_universitario'] ?? null) ?? '',
                nombre: $this->nombre($columnas),
                grupo: $this->texto($columnas['grupo'] ?? null),
                aula: $this->texto($columnas['aula'] ?? null),
                hora: substr($this->instante($columnas['registrado_en'] ?? null), 11),
                medio: match ($this->texto($columnas['medio_identificacion'] ?? null)) {
                    'qr' => 'Código QR',
                    'codigo' => 'Código SIS',
                    'documento' => 'C.I.',
                    default => 'Sin dato',
                },
                expulsado: (bool) ($columnas['expulsado'] ?? false),
                excepcion: (bool) ($columnas['es_excepcion'] ?? false),
            );
        }

        return $asistencia;
    }

    /**
     * @return list<FilaAusente>
     */
    public function ausentes(int $examenId): array
    {
        $filas = DB::table('habilitaciones as habilitacion')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'habilitacion.estudiante_id')
            ->leftJoin('carreras as carrera', 'carrera.id', '=', 'estudiante.carrera_id')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'habilitacion.aula_id')
            ->where('habilitacion.examen_id', $examenId)
            // El no habilitado no es un ausente: no se lo esperaba.
            ->where('habilitacion.habilitado', true)
            ->whereNotExists(function (Builder $existe): void {
                $existe->selectRaw('1')
                    ->from('ingresos as ingreso')
                    ->whereColumn('ingreso.estudiante_id', 'habilitacion.estudiante_id')
                    ->whereColumn('ingreso.examen_id', 'habilitacion.examen_id');
            })
            ->orderByRaw("translate(lower(estudiante.apellidos), 'áéíóúüñ', 'aeiouun')")
            ->orderByRaw("translate(lower(estudiante.nombres), 'áéíóúüñ', 'aeiouun')")
            ->orderBy('estudiante.id')
            ->select([
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
                'carrera.nombre as carrera',
                'aula.nombre as aula',
            ])
            ->selectRaw(sprintf(self::GRUPO_DEL_ESTUDIANTE, 'habilitacion.examen_id'))
            ->get();

        $ausentes = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $ausentes[] = new FilaAusente(
                codigoUniversitario: $this->texto($columnas['codigo_universitario'] ?? null) ?? '',
                nombre: $this->nombre($columnas),
                grupo: $this->texto($columnas['grupo'] ?? null),
                carrera: $this->texto($columnas['carrera'] ?? null),
                aula: $this->texto($columnas['aula'] ?? null),
            );
        }

        return $ausentes;
    }

    /**
     * @return array<string, string>
     */
    public function nombresDelAlcance(AlcanceReporte $alcance): array
    {
        $nombres = [];

        if ($alcance->examenId !== null) {
            $examen = $this->examen($alcance->examenId);

            if ($examen !== null) {
                $nombres['examen'] = sprintf(
                    '%s · %s (%s, %s)',
                    $examen->asignatura,
                    $examen->tipo,
                    FechaLegible::dia($examen->fecha),
                    $examen->hora,
                );
            }

            return $nombres;
        }

        if ($alcance->periodoId !== null) {
            $periodo = $this->texto(
                DB::table('periodos')->where('id', $alcance->periodoId)->value('codigo')
            );

            if ($periodo !== null) {
                $nombres['periodo'] = $periodo;
            }
        }

        if ($alcance->facultad !== null) {
            $sigla = $this->texto(
                DB::table('facultades')->where('clave', $alcance->facultad)->value('sigla')
            );

            if ($sigla !== null) {
                $nombres['facultad'] = $sigla;
            }
        }

        return $nombres;
    }

    public function examen(int $examenId): ?ExamenReporteData
    {
        $fila = DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->where('examen.id', $examenId)
            ->select([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'asignatura.nombre as asignatura',
            ])
            ->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return new ExamenReporteData(
            id: $this->entero($columnas['id'] ?? null),
            asignatura: $this->texto($columnas['asignatura'] ?? null) ?? '',
            tipo: $this->tipo($columnas['tipo'] ?? null),
            fecha: $this->fecha($columnas['fecha'] ?? null),
            hora: $this->hora($columnas['hora_inicio'] ?? null),
        );
    }

    /**
     * @return list<FilaMiExamen>
     */
    public function examenesDelDocente(int $usuarioId, ?int $periodoId): array
    {
        $hoy = now()->toDateString();

        $consulta = DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->where(function (Builder $propio) use ($usuarioId): void {
                $propio->where('examen.creado_por', $usuarioId)
                    ->orWhereExists(function (Builder $existe) use ($usuarioId): void {
                        $existe->selectRaw('1')
                            ->from('examen_grupo as eg')
                            ->join('grupos as grupo', 'grupo.id', '=', 'eg.grupo_id')
                            ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
                            ->whereColumn('eg.examen_id', 'examen.id')
                            ->where('docente.user_id', $usuarioId);
                    });
            });

        if ($periodoId !== null) {
            $consulta->where('examen.periodo_id', $periodoId);
        } else {
            $consulta->whereExists(function (Builder $vigente) use ($hoy): void {
                $vigente->selectRaw('1')
                    ->from('periodos as periodo')
                    ->whereColumn('periodo.id', 'examen.periodo_id')
                    ->where('periodo.fecha_inicio', '<=', $hoy)
                    ->where('periodo.fecha_fin', '>=', $hoy);
            });
        }

        $filas = $consulta
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->select([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'asignatura.id as asignatura_id',
                'asignatura.nombre as asignatura',
            ])
            ->selectRaw(
                '(select count(*) from ingresos as ingreso where ingreso.examen_id = examen.id) as presentes'
            )
            ->selectRaw(
                '(select count(*) from habilitaciones as h where h.examen_id = examen.id and h.habilitado) as habilitados'
            )
            ->selectRaw(
                '(select count(*) from habilitaciones as h where h.examen_id = examen.id'
                .' and not h.habilitado) as no_habilitados'
            )
            ->selectRaw(
                '(select count(*) from habilitaciones as h where h.examen_id = examen.id and h.habilitado'
                .' and not exists (select 1 from ingresos as i where i.examen_id = h.examen_id'
                .' and i.estudiante_id = h.estudiante_id)) as ausentes'
            )
            ->selectRaw(
                '(select count(*) from incidencias as incidencia where '.$this->incidenciaDelExamen().') as incidencias',
                [$this->apertura()],
            )
            ->get();

        $examenes = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $fecha = $this->fecha($columnas['fecha'] ?? null);

            $examenes[] = new FilaMiExamen(
                id: $this->entero($columnas['id'] ?? null),
                asignaturaId: $this->entero($columnas['asignatura_id'] ?? null),
                asignatura: $this->texto($columnas['asignatura'] ?? null) ?? '',
                tipo: $this->tipo($columnas['tipo'] ?? null),
                fecha: $fecha,
                hora: $this->hora($columnas['hora_inicio'] ?? null),
                presentes: $this->entero($columnas['presentes'] ?? 0),
                habilitados: $this->entero($columnas['habilitados'] ?? 0),
                noHabilitados: $this->entero($columnas['no_habilitados'] ?? 0),
                ausentes: $this->entero($columnas['ausentes'] ?? 0),
                incidencias: $this->entero($columnas['incidencias'] ?? 0),
                // Rendido: de un dia anterior, o de hoy y ya empezado.
                rendido: $fecha < $hoy || ($fecha === $hoy && $this->hora($columnas['hora_inicio'] ?? null) <= now()->format('H:i')),
            );
        }

        return $examenes;
    }

    public function grupo(int $grupoId): ?GrupoReporteData
    {
        $fila = DB::table('grupos as grupo')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'grupo.asignatura_id')
            ->join('periodos as periodo', 'periodo.id', '=', 'grupo.periodo_id')
            ->where('grupo.id', $grupoId)
            ->select([
                'grupo.id',
                'grupo.codigo',
                'asignatura.codigo as asignatura_codigo',
                'asignatura.nombre as asignatura',
                'periodo.codigo as periodo',
            ])
            ->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return new GrupoReporteData(
            id: $this->entero($columnas['id'] ?? null),
            codigo: $this->texto($columnas['codigo'] ?? null) ?? '',
            asignaturaCodigo: $this->texto($columnas['asignatura_codigo'] ?? null) ?? '',
            asignatura: $this->texto($columnas['asignatura'] ?? null) ?? '',
            periodo: $this->texto($columnas['periodo'] ?? null) ?? '',
        );
    }

    /**
     * @return list<FilaEstudianteGrupo>
     */
    public function estudiantesDeGrupo(int $grupoId): array
    {
        $filas = DB::table('inscripciones as inscripcion')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'inscripcion.estudiante_id')
            ->where('inscripcion.grupo_id', $grupoId)
            ->orderByRaw("translate(lower(estudiante.apellidos), 'áéíóúüñ', 'aeiouun')")
            ->orderByRaw("translate(lower(estudiante.nombres), 'áéíóúüñ', 'aeiouun')")
            ->orderBy('estudiante.id')
            ->select([
                'estudiante.codigo_universitario',
                'estudiante.documento_identidad',
                'estudiante.nombres',
                'estudiante.apellidos',
                'estudiante.verificado',
            ])
            ->get();

        $estudiantes = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $estudiantes[] = new FilaEstudianteGrupo(
                codigoUniversitario: $this->texto($columnas['codigo_universitario'] ?? null) ?? '',
                nombre: $this->nombre($columnas),
                documento: $this->texto($columnas['documento_identidad'] ?? null),
                verificado: (bool) ($columnas['verificado'] ?? false),
            );
        }

        return $estudiantes;
    }

    /**
     * Los examenes del alcance: solo el elegido o los que dejan los
     * filtros. Deja unidos `asignatura`, `creador` y `docente`.
     */
    private function examenesDe(AlcanceReporte $alcance): Builder
    {
        $consulta = DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->join('usuarios as creador', 'creador.id', '=', 'examen.creado_por')
            ->leftJoin('docentes as docente', 'docente.user_id', '=', 'examen.creado_por');

        if ($alcance->examenId !== null) {
            return $consulta->where('examen.id', $alcance->examenId);
        }

        if ($alcance->periodoId !== null) {
            $consulta->where('examen.periodo_id', $alcance->periodoId);
        }

        if ($alcance->facultad !== null) {
            $consulta->whereRaw(
                self::FACULTAD_DEL_EXAMEN.' = (select filtro_f.id from facultades as filtro_f where filtro_f.clave = ?)',
                [$alcance->facultad],
            );
        }

        if ($alcance->buscar !== null) {
            $patron = '%'.$this->sinTildes($alcance->buscar).'%';

            $consulta->where(function (Builder $texto) use ($patron): void {
                $texto->whereRaw($this->normalizada('asignatura.nombre').' like ?', [$patron])
                    ->orWhereRaw($this->normalizada(self::DOCENTE).' like ?', [$patron]);
            });
        }

        if ($alcance->desde !== null) {
            $consulta->where('examen.fecha', '>=', $alcance->desde);
        }

        if ($alcance->hasta !== null) {
            $consulta->where('examen.fecha', '<=', $alcance->hasta);
        }

        return $consulta;
    }

    private function idsDe(AlcanceReporte $alcance): Builder
    {
        return $this->examenesDe($alcance)->select('examen.id');
    }

    /**
     * Intentos que rechazo la puerta: los de un examen del alcance y los
     * que quedaron sin examen pero ocurrieron en un aula de uno del
     * alcance, dentro de su ventana (desde la apertura hasta el final).
     */
    private function intentosDe(AlcanceReporte $alcance): Builder
    {
        $apertura = $this->apertura();

        // El ingreso repetido tambien lo asienta la puerta, pero es una
        // incidencia del examen, no un intento rechazado.
        return DB::table('incidencias as incidencia')
            ->where('incidencia.origen', 'SISTEMA')
            ->where('incidencia.tipo', 'INTENTO_NO_AUTORIZADO')
            ->where(function (Builder $delAlcance) use ($alcance, $apertura): void {
                $delAlcance
                    ->whereIn('incidencia.examen_id', $this->idsDe($alcance))
                    ->orWhere(function (Builder $sinExamen) use ($alcance, $apertura): void {
                        $sinExamen
                            ->whereNull('incidencia.examen_id')
                            ->whereExists(function (Builder $ventana) use ($alcance, $apertura): void {
                                $ventana->selectRaw('1')
                                    ->from('examenes as ventana')
                                    ->join('examen_aula as puerta', 'puerta.examen_id', '=', 'ventana.id')
                                    ->whereIn('ventana.id', $this->idsDe($alcance))
                                    ->whereColumn('puerta.aula_id', 'incidencia.aula_id')
                                    ->whereRaw(sprintf(self::EN_LA_VENTANA, 'ventana'), [$apertura]);
                            });
                    });
            });
    }

    /**
     * Todo lo asentado en los examenes del alcance: lo que lleva el
     * `examen_id` de uno de ellos y los rechazos de la puerta que se le
     * atribuyen por aula y ventana.
     */
    private function incidenciasDe(AlcanceReporte $alcance): Builder
    {
        return DB::table('incidencias as incidencia')
            ->where(function (Builder $delAlcance) use ($alcance): void {
                $delAlcance
                    ->whereIn('incidencia.examen_id', $this->idsDe($alcance))
                    ->orWhereIn('incidencia.id', $this->intentosDe($alcance)->select('incidencia.id'));
            });
    }

    /**
     * Pares estudiante y examen del alcance: los inscritos en un grupo del
     * examen, los que tienen condicion registrada y los que ingresaron.
     */
    private function participantesDe(AlcanceReporte $alcance): Builder
    {
        return DB::table('inscripciones as inscripcion')
            ->join('examen_grupo as eg', 'eg.grupo_id', '=', 'inscripcion.grupo_id')
            ->whereIn('eg.examen_id', $this->idsDe($alcance))
            ->select(['inscripcion.estudiante_id', 'eg.examen_id'])
            ->union(
                DB::table('habilitaciones')
                    ->whereIn('examen_id', $this->idsDe($alcance))
                    ->select(['estudiante_id', 'examen_id'])
            )
            ->union(
                DB::table('ingresos')
                    ->whereIn('examen_id', $this->idsDe($alcance))
                    ->select(['estudiante_id', 'examen_id'])
            );
    }

    /**
     * Los estudiantes del resumen: los que participan en un examen del
     * alcance y los identificados en algo que quedo asentado.
     */
    private function implicadosDe(AlcanceReporte $alcance): Builder
    {
        return DB::query()
            ->fromSub($this->participantesDe($alcance), 'participante')
            ->select('participante.estudiante_id')
            ->union(
                $this->incidenciasDe($alcance)
                    ->whereNotNull('incidencia.estudiante_id')
                    ->select('incidencia.estudiante_id')
            );
    }

    /**
     * Nombres separados por `chr(31)`, en orden alfabetico sin contar
     * tildes ni mayusculas, unidos con comas.
     */
    private function enOrden(string $nombres): string
    {
        $lista = $nombres === '' ? [] : explode(chr(31), $nombres);

        usort($lista, fn (string $a, string $b): int => [$this->sinTildes($a), $a] <=> [$this->sinTildes($b), $b]);

        return implode(', ', $lista);
    }

    private function incidenciaDelExamen(): string
    {
        return sprintf(self::INCIDENCIA_DEL_EXAMEN, sprintf(self::EN_LA_VENTANA, 'examen'));
    }

    /**
     * Minutos antes del inicio en que abre la puerta.
     */
    private function apertura(): int
    {
        $apertura = config('umss.minutos_apertura', 60);

        return is_numeric($apertura) ? (int) $apertura : 60;
    }

    /**
     * Expresion SQL «Asignatura · Tipo» de un examen, con la etiqueta del
     * tipo. Los textos salen de `TIPOS`, no de la peticion.
     */
    private function examenComoTexto(string $examen, string $asignatura): string
    {
        $casos = '';

        foreach (self::TIPOS as $clave => $etiqueta) {
            $casos .= sprintf(" when '%s' then '%s'", $clave, $etiqueta);
        }

        return "{$asignatura}.nombre || ' · ' || (case {$examen}.tipo{$casos} else {$examen}.tipo end)";
    }

    /**
     * Cuenta filas de una tabla con `examen_id` y `aula_id`, por examen y
     * aula. El aula nula va en la clave 0.
     *
     * @return array<int, array<int, int>>
     */
    private function porExamenYAula(Builder $tabla, AlcanceReporte $alcance): array
    {
        $filas = $tabla
            ->whereIn('examen_id', $this->idsDe($alcance))
            ->groupBy('examen_id', 'aula_id')
            ->selectRaw('examen_id, aula_id, count(*) as cantidad')
            ->get();

        $mapa = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $aulaId = $columnas['aula_id'] ?? null;

            $mapa[$this->entero($columnas['examen_id'] ?? null)][$aulaId === null ? 0 : $this->entero($aulaId)]
                = $this->entero($columnas['cantidad'] ?? 0);
        }

        return $mapa;
    }

    private function tramo(string $marca): string
    {
        $hora = substr($marca, 11, 2);
        $minuto = (int) substr($marca, 14, 2);
        $desde = intdiv($minuto, self::TRAMO) * self::TRAMO;

        // Con el dia: el mismo tramo de dos dias no se suma.
        return sprintf('%s %s:%02d', substr($marca, 0, 10), $hora, $desde);
    }

    /**
     * Expresion SQL de una columna en minusculas y sin tildes.
     */
    private function normalizada(string $expresion): string
    {
        return "translate(lower({$expresion}), 'áéíóúüñ', 'aeiouun')";
    }

    private function sinTildes(string $texto): string
    {
        $texto = strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        // Los comodines que teclee el usuario se buscan como texto.
        return addcslashes($texto, '%_\\');
    }

    private function tipo(mixed $valor): string
    {
        $tipo = $this->texto($valor) ?? '';

        return self::TIPOS[$tipo] ?? $tipo;
    }

    /**
     * `AAAA-MM-DD`.
     */
    private function fecha(mixed $valor): string
    {
        return substr($this->texto($valor) ?? '', 0, 10);
    }

    /**
     * `HH:MM`.
     */
    private function hora(mixed $valor): string
    {
        return substr($this->texto($valor) ?? '', 0, 5);
    }

    /**
     * `AAAA-MM-DD HH:MM:SS`, sin fracciones de segundo.
     */
    private function instante(mixed $valor): string
    {
        return substr($this->texto($valor) ?? '', 0, 19);
    }

    /**
     * @param  array<string, mixed>  $columnas
     */
    private function nombre(array $columnas): string
    {
        $nombres = $this->texto($columnas['nombres'] ?? null) ?? '';
        $apellidos = $this->texto($columnas['apellidos'] ?? null) ?? '';

        return trim("{$apellidos} {$nombres}");
    }

    /**
     * @return array<string, mixed>
     */
    private function columnas(object $fila): array
    {
        /** @var array<string, mixed> $columnas */
        $columnas = get_object_vars($fila);

        return $columnas;
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
}
