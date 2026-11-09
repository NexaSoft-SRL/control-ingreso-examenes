<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Persistence;

use App\Modules\Reportes\Application\Contracts\ConsultaBitacoraExamenGateway;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class EloquentConsultaBitacoraExamenGateway implements ConsultaBitacoraExamenGateway
{
    private const POR_PAGINA = 100;

    private const CON_TILDE = 'áéíóúüñÁÉÍÓÚÜÑ';

    private const SIN_TILDE = 'aeiouunaeiouun';

    private const TIPOS_EXAMEN = [
        'PRIMER_PARCIAL' => 'Primer parcial',
        'SEGUNDO_PARCIAL' => 'Segundo parcial',
        'FINAL' => 'Examen final',
        'SEGUNDA_INSTANCIA' => 'Segunda instancia',
        'MESA' => 'Examen de mesa',
    ];

    /**
     * @var array<string, string>
     */
    private const CATEGORIAS = [
        'INGRESO' => 'Ingresos',
        'RECHAZO' => 'Rechazos',
        'INCIDENCIA' => 'Incidencias',
        'HABILITACION' => 'Habilitación',
        'CODIGO_QR' => 'Códigos QR',
        'ACCESO_AULA' => 'Acceso a aula',
        'ADMINISTRACION' => 'Operaciones administrativas',
        'CIERRE' => 'Cierre',
        'AUSENTE' => 'No se presentaron',
    ];

    /**
     * El examen ya termino: se finalizo a mano o paso su dia.
     */
    private const CERRADO = '(examen.finalizado_en is not null or examen.fecha < current_date)';

    /**
     * El instante del cierre: la finalizacion o el fin de su dia.
     */
    private const INSTANTE_CIERRE = "coalesce(examen.finalizado_en, examen.fecha + time '23:59:59')";

    public function examen(int $examenId): ?array
    {
        $fila = DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->where('examen.id', $examenId)
            ->first([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'asignatura.nombre as asignatura',
            ]);

        if ($fila === null) {
            return null;
        }

        $columnas = get_object_vars($fila);
        $tipo = self::texto($columnas['tipo'] ?? null) ?? '';
        $fecha = self::texto($columnas['fecha'] ?? null) ?? '';

        return [
            'id' => self::entero($columnas['id'] ?? null),
            'tipo' => self::TIPOS_EXAMEN[$tipo] ?? $tipo,
            'asignatura' => self::texto($columnas['asignatura'] ?? null) ?? '',
            'fecha' => substr($fecha, 0, 10),
            'hora' => substr(self::texto($columnas['hora_inicio'] ?? null) ?? '', 0, 5),
        ];
    }

    public function existe(int $examenId): bool
    {
        return DB::table('examenes')->where('id', $examenId)->exists();
    }

    public function examenesVisibles(int $usuarioId, bool $universidad): array
    {
        $consulta = DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id');

        if (! $universidad) {
            $consulta->where(static function (Builder $alcance) use ($usuarioId): void {
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
        }

        $filas = $consulta
            ->orderByDesc('examen.fecha')
            ->orderByDesc('examen.hora_inicio')
            ->orderByDesc('examen.id')
            ->get([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'asignatura.nombre as asignatura',
            ]);

        $examenes = [];

        foreach ($filas as $fila) {
            $columnas = get_object_vars($fila);
            $tipo = self::texto($columnas['tipo'] ?? null) ?? '';
            $fecha = self::texto($columnas['fecha'] ?? null) ?? '';

            $examenes[] = [
                'id' => self::entero($columnas['id'] ?? null),
                'tipo' => self::TIPOS_EXAMEN[$tipo] ?? $tipo,
                'asignatura' => self::texto($columnas['asignatura'] ?? null) ?? '',
                'fecha' => substr($fecha, 0, 10),
            ];
        }

        return $examenes;
    }

    public function aulas(int $examenId): array
    {
        $filas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->where('asignada.examen_id', $examenId)
            ->orderBy('aula.nombre')
            ->get(['aula.id', 'aula.nombre']);

        $aulas = [];

        foreach ($filas as $fila) {
            $columnas = get_object_vars($fila);
            $aulas[] = [
                'id' => self::entero($columnas['id'] ?? null),
                'nombre' => self::texto($columnas['nombre'] ?? null) ?? '',
            ];
        }

        return $aulas;
    }

    public function categorias(int $examenId): array
    {
        $categorias = [];
        $filas = $this->consulta($examenId)
            ->select('evento.categoria')
            ->selectRaw('count(*) as total')
            ->groupBy('evento.categoria')
            ->orderBy('evento.categoria')
            ->get();

        foreach ($filas as $fila) {
            $columnas = get_object_vars($fila);
            $valor = self::texto($columnas['categoria'] ?? null) ?? '';

            if (isset(self::CATEGORIAS[$valor])) {
                $categorias[] = [
                    'valor' => $valor,
                    'etiqueta' => self::CATEGORIAS[$valor],
                    'total' => self::entero($columnas['total'] ?? 0),
                ];
            }
        }

        return $categorias;
    }

    public function listar(
        int $examenId,
        ?string $categoria,
        ?string $buscar,
        ?int $aulaId,
        int $pagina,
    ): array {
        $consulta = $this->consulta($examenId);

        if ($categoria !== null) {
            $consulta->where('evento.categoria', $categoria);
        }

        if ($aulaId !== null) {
            $consulta->where(function (Builder $aula) use ($aulaId): void {
                $aula->whereNull('evento.aula_id')
                    ->orWhere('evento.aula_id', $aulaId);
            });
        }

        if ($buscar !== null) {
            $palabras = preg_split('/\s+/u', self::normalizar($buscar), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($palabras as $palabra) {
                $consulta->whereRaw(
                    "translate(lower(concat_ws(' ', evento.estudiante, evento.codigo, evento.detalle)), ?, ?) like ?",
                    [
                        self::CON_TILDE,
                        self::SIN_TILDE,
                        '%'.addcslashes($palabra, '\\%_').'%',
                    ],
                );
            }
        }

        $total = (clone $consulta)->count();
        $totalExamen = $this->consulta($examenId)->count();
        $filas = $consulta
            ->orderByDesc('evento.ocurrida_en')
            ->orderByDesc('evento.id')
            ->forPage($pagina, self::POR_PAGINA)
            ->get();

        $eventos = [];

        foreach ($filas as $fila) {
            $eventos[] = $this->evento($fila);
        }

        return [
            'data' => $eventos,
            'meta' => [
                'total' => $total,
                'total_examen' => $totalExamen,
                'pagina' => $pagina,
                'por_pagina' => self::POR_PAGINA,
                'paginas' => (int) ceil($total / self::POR_PAGINA),
            ],
        ];
    }

    public function todos(int $examenId): array
    {
        $filas = $this->consulta($examenId)
            ->orderByDesc('evento.ocurrida_en')
            ->orderByDesc('evento.id')
            ->get();

        $eventos = [];

        foreach ($filas as $fila) {
            $eventos[] = $this->evento($fila);
        }

        return $eventos;
    }

    private function consulta(int $examenId): Builder
    {
        $union = $this->ingresos($examenId)
            ->unionAll($this->incidencias($examenId))
            ->unionAll($this->accesosCodigoAula($examenId))
            ->unionAll($this->operaciones($examenId))
            ->unionAll($this->cierre($examenId))
            ->unionAll($this->ausentes($examenId));

        return DB::query()->fromSub($union, 'evento');
    }

    /**
     * Al cerrar: cuantos ingresaron de los habilitados, cuantos no se
     * presentaron y cuantas incidencias e intentos rechazados hubo.
     */
    private function cierre(int $examenId): Builder
    {
        $habilitados = '(select count(*) from habilitaciones as h where h.examen_id = examen.id and h.habilitado)';
        $ingresaron = '(select count(distinct i.estudiante_id) from ingresos as i where i.examen_id = examen.id)';
        $ausentes = '(select count(*) from habilitaciones as h where h.examen_id = examen.id and h.habilitado'
            .' and not exists (select 1 from ingresos as i where i.examen_id = h.examen_id and i.estudiante_id = h.estudiante_id))';
        $incidencias = "(select count(*) from incidencias as n where n.examen_id = examen.id and n.origen <> 'SISTEMA')";
        $rechazos = "(select count(*) from incidencias as n where n.examen_id = examen.id and n.origen = 'SISTEMA')";

        return DB::table('examenes as examen')
            ->where('examen.id', $examenId)
            ->whereRaw(self::CERRADO)
            ->selectRaw(
                "concat('cierre-', examen.id) as id, ".self::INSTANTE_CIERRE.' as ocurrida_en, '
                ."'CIERRE' as categoria, 'Cierre del examen' as suceso, '' as estudiante, "
                .'null as codigo, null::bigint as aula_id, null as aula, '
                ."concat('Ingresaron ', {$ingresaron}, ' de ', {$habilitados}, ' habilitados · ', "
                ."{$ausentes}, ' no se presentaron · ', {$incidencias}, ' incidencias · ', "
                ."{$rechazos}, ' intentos rechazados') as detalle, "
                ."'Sistema' as actor",
            );
    }

    /**
     * Al cerrar, cada habilitado que no entro, con el aula que tenia: buscar
     * al estudiante responde un reclamo tambien cuando no vino.
     */
    private function ausentes(int $examenId): Builder
    {
        return DB::table('habilitaciones as habilitacion')
            ->join('examenes as examen', 'examen.id', '=', 'habilitacion.examen_id')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'habilitacion.estudiante_id')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'habilitacion.aula_id')
            ->where('habilitacion.examen_id', $examenId)
            ->where('habilitacion.habilitado', true)
            ->whereRaw(self::CERRADO)
            ->whereNotExists(static function (Builder $ingreso): void {
                $ingreso->selectRaw('1')
                    ->from('ingresos')
                    ->whereColumn('ingresos.examen_id', 'habilitacion.examen_id')
                    ->whereColumn('ingresos.estudiante_id', 'habilitacion.estudiante_id');
            })
            ->selectRaw(
                "concat('ausente-', habilitacion.id) as id, ".self::INSTANTE_CIERRE.' as ocurrida_en, '
                ."'AUSENTE' as categoria, 'No se presentó' as suceso, "
                ."trim(concat_ws(' ', estudiante.apellidos, estudiante.nombres)) as estudiante, "
                .'estudiante.codigo_universitario as codigo, aula.id as aula_id, aula.nombre as aula, '
                ."'Habilitado' as detalle, 'Sistema' as actor",
            );
    }

    private function ingresos(int $examenId): Builder
    {
        return DB::table('ingresos as ingreso')
            ->leftJoin('estudiantes as estudiante', 'estudiante.id', '=', 'ingreso.estudiante_id')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'ingreso.aula_id')
            ->leftJoin('habilitaciones as habilitacion', static function (JoinClause $join): void {
                $join->on('habilitacion.examen_id', '=', 'ingreso.examen_id')
                    ->on('habilitacion.estudiante_id', '=', 'ingreso.estudiante_id');
            })
            ->leftJoin('aulas as aula_sugerida', 'aula_sugerida.id', '=', 'habilitacion.aula_id')
            ->join('examenes as examen', 'examen.id', '=', 'ingreso.examen_id')
            ->leftJoin('usuarios as actor', 'actor.id', '=', 'ingreso.registrado_por')
            ->leftJoin('accesos_codigo_aula as acceso', 'acceso.id', '=', 'ingreso.acceso_codigo_aula_id')
            ->where('ingreso.examen_id', $examenId)
            ->selectRaw(
                "concat('ingreso-', ingreso.id) as id, ingreso.registrado_en as ocurrida_en, "
                ."'INGRESO' as categoria, 'Ingreso autorizado' as suceso, "
                ."coalesce(nullif(trim(concat_ws(' ', estudiante.apellidos, estudiante.nombres)), ''), 'Sin identificar') as estudiante, "
                .'estudiante.codigo_universitario as codigo, aula.id as aula_id, aula.nombre as aula, '
                ."concat_ws(' · ', "
                ."case lower(ingreso.medio_identificacion) when 'qr' then 'Código QR' "
                ."when 'codigo' then 'Código SIS' when 'documento' then 'C.I.' "
                ."else nullif(trim(ingreso.medio_identificacion), '') end, "
                ."'Identidad verificada correctamente', "
                ."case when ingreso.es_excepcion then concat('Excepción: ', "
                ."coalesce(nullif(trim(ingreso.motivo_excepcion), ''), 'ingreso autorizado')) end, "
                .'case when habilitacion.aula_id is not null and ingreso.aula_id is distinct from habilitacion.aula_id '
                ."then concat('Aula sugerida: ', aula_sugerida.nombre) end, "
                .'case when ingreso.registrado_en > (examen.fecha + examen.hora_inicio) '
                ."then 'Ingreso posterior al inicio' end) as detalle, "
                ."coalesce(acceso.nombre_colaborador, actor.nombre, 'Sistema') as actor",
            );
    }

    private function accesosCodigoAula(int $examenId): Builder
    {
        $solicitudes = DB::table('accesos_codigo_aula as acceso')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'acceso.aula_id')
            ->where('acceso.examen_id', $examenId)
            ->whereNotExists(static function (Builder $auditoria): void {
                $auditoria->selectRaw('1')
                    ->from('bitacora_operaciones as operacion')
                    ->where('operacion.operacion', 'acceso_codigo_aula.ingreso')
                    ->where('operacion.tabla_afectada', 'accesos_codigo_aula')
                    ->whereColumn('operacion.registro_id', 'acceso.id');
            })
            ->selectRaw(
                "concat('acceso-solicitud-', acceso.id) as id, acceso.created_at as ocurrida_en, "
                ."'ACCESO_AULA' as categoria, 'acceso_codigo_aula.ingreso' as suceso, "
                .'acceso.nombre_colaborador as estudiante, cast(null as varchar) as codigo, '
                .'aula.id as aula_id, aula.nombre as aula, '
                ."concat(acceso.nombre_colaborador, ' pidió controlar el aula ', aula.nombre, "
                ."' con su código; espera la autorización del docente.') as detalle, "
                ."concat(acceso.nombre_colaborador, ' (código de aula)') as actor",
            );

        $resoluciones = DB::table('accesos_codigo_aula as acceso')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'acceso.aula_id')
            ->leftJoin('usuarios as actor', 'actor.id', '=', 'acceso.resuelto_por')
            ->where('acceso.examen_id', $examenId)
            ->whereIn('acceso.estado', ['AUTORIZADO', 'RECHAZADO', 'CORTADO'])
            ->whereNotExists(static function (Builder $auditoria): void {
                $auditoria->selectRaw('1')
                    ->from('bitacora_operaciones as operacion')
                    ->where('operacion.tabla_afectada', 'accesos_codigo_aula')
                    ->whereColumn('operacion.registro_id', 'acceso.id')
                    ->where(static function (Builder $accion): void {
                        $accion
                            ->where(static function (Builder $autorizacion): void {
                                $autorizacion
                                    ->where('acceso.estado', 'AUTORIZADO')
                                    ->whereIn('operacion.operacion', [
                                        'acceso_aula.autorizar',
                                        'acceso_aula.trasladar',
                                    ]);
                            })
                            ->orWhere(static function (Builder $rechazo): void {
                                $rechazo
                                    ->where('acceso.estado', 'RECHAZADO')
                                    ->where('operacion.operacion', 'acceso_aula.rechazar');
                            })
                            ->orWhere(static function (Builder $corte): void {
                                $corte
                                    ->where('acceso.estado', 'CORTADO')
                                    ->where('operacion.operacion', 'acceso_aula.cortar');
                            });
                    });
            })
            ->selectRaw(
                "concat('acceso-resolucion-', acceso.id) as id, "
                .'coalesce(acceso.resuelto_en, acceso.updated_at, acceso.created_at) as ocurrida_en, '
                ."'ACCESO_AULA' as categoria, "
                ."case acceso.estado when 'AUTORIZADO' then 'acceso_aula.autorizar' "
                ."when 'RECHAZADO' then 'acceso_aula.rechazar' else 'acceso_aula.cortar' end as suceso, "
                .'acceso.nombre_colaborador as estudiante, cast(null as varchar) as codigo, '
                .'aula.id as aula_id, aula.nombre as aula, '
                ."case acceso.estado when 'AUTORIZADO' then concat(acceso.nombre_colaborador, "
                ."' fue autorizado a controlar el aula ', aula.nombre, '.') "
                ."when 'RECHAZADO' then concat(acceso.nombre_colaborador, "
                ."' no fue autorizado a controlar el aula ', aula.nombre, '.') "
                ."else concat('Se cortó el acceso de ', acceso.nombre_colaborador, "
                ."' al aula ', aula.nombre, '.') end as detalle, "
                ."coalesce(actor.nombre, 'Sistema') as actor",
            );

        return $solicitudes->unionAll($resoluciones);
    }

    private function incidencias(int $examenId): Builder
    {
        return DB::table('incidencias as incidencia')
            ->leftJoin('estudiantes as estudiante', 'estudiante.id', '=', 'incidencia.estudiante_id')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'incidencia.aula_id')
            ->leftJoin('usuarios as actor', 'actor.id', '=', 'incidencia.reportada_por')
            ->leftJoin('accesos_codigo_aula as acceso', 'acceso.id', '=', 'incidencia.acceso_codigo_aula_id')
            ->where('incidencia.examen_id', $examenId)
            ->selectRaw(
                "concat('incidencia-', incidencia.id) as id, incidencia.ocurrida_en as ocurrida_en, "
                ."case when incidencia.origen = 'SISTEMA' then 'RECHAZO' else 'INCIDENCIA' end as categoria, "
                ."case when incidencia.tipo = 'INGRESO_REPETIDO' then 'Ingreso repetido' "
                ."when incidencia.origen = 'SISTEMA' then 'Ingreso rechazado' "
                ."when incidencia.tipo = 'EXPULSION' then 'Expulsión registrada' "
                ."else 'Incidencia reportada' end as suceso, "
                ."coalesce(nullif(trim(concat_ws(' ', estudiante.apellidos, estudiante.nombres)), ''), "
                ."nullif(incidencia.estudiante_texto, ''), 'Sin identificar') as estudiante, "
                .'estudiante.codigo_universitario as codigo, aula.id as aula_id, aula.nombre as aula, '
                .'incidencia.detalle as detalle, '
                ."coalesce(acceso.nombre_colaborador, actor.nombre, 'Sistema') as actor",
            );
    }

    private function operaciones(int $examenId): Builder
    {
        $consulta = DB::table('bitacora_operaciones as operacion')
            ->leftJoin('usuarios as actor', 'actor.id', '=', 'operacion.usuario_id')
            ->leftJoin('ingresos as ingreso', static function (JoinClause $join): void {
                $join->on('ingreso.id', '=', 'operacion.registro_id')
                    ->where('operacion.tabla_afectada', '=', 'ingresos');
            })
            ->leftJoin('incidencias as incidencia', static function (JoinClause $join): void {
                $join->on('incidencia.id', '=', 'operacion.registro_id')
                    ->where('operacion.tabla_afectada', '=', 'incidencias');
            })
            ->leftJoin('examen_aula_codigos as codigo_aula', static function (JoinClause $join): void {
                $join->on('codigo_aula.id', '=', 'operacion.registro_id')
                    ->where('operacion.tabla_afectada', '=', 'examen_aula_codigos');
            })
            ->leftJoin('accesos_codigo_aula as acceso', static function (JoinClause $join): void {
                $join->on('acceso.id', '=', 'operacion.registro_id')
                    ->where('operacion.tabla_afectada', '=', 'accesos_codigo_aula');
            })
            ->leftJoin('estudiantes as estudiante', static function (JoinClause $join): void {
                $join->on('estudiante.id', '=', DB::raw('coalesce(ingreso.estudiante_id, incidencia.estudiante_id)'));
            })
            ->leftJoin('aulas as aula', static function (JoinClause $join): void {
                $join->on('aula.id', '=', DB::raw(
                    'coalesce(ingreso.aula_id, incidencia.aula_id, codigo_aula.aula_id, acceso.aula_id)',
                ));
            })
            ->whereIn('operacion.operacion', [
                'examen.registrar',
                'examen.modificar',
                'examen.eliminar',
                'examen.finalizar',
                'examen.mover_personal',
                'examen.mover_estudiantes',
                'habilitacion.habilitar',
                'habilitacion.inhabilitar',
                'habilitacion.repartir',
                'habilitacion.sin_foto',
                'qr.emitir',
                'qr.enviar',
                'aviso.enviar',
                'examen_aula_codigo.generar',
                'examen_aula_codigo.regenerar',
                'acceso_codigo_aula.ingreso',
                'acceso_aula.autorizar',
                'acceso_aula.rechazar',
                'acceso_aula.cortar',
                'acceso_aula.trasladar',
                'puerta.excepcion',
                'incidencia.atender',
                'incidencia.corregir',
            ])
            ->where(static function (Builder $relacion) use ($examenId): void {
                $relacion
                    ->where(static function (Builder $examen) use ($examenId): void {
                        $examen->where('operacion.tabla_afectada', 'examenes')
                            ->where('operacion.registro_id', $examenId);
                    })
                    ->orWhere(static function (Builder $habilitaciones) use ($examenId): void {
                        $habilitaciones->where('operacion.tabla_afectada', 'habilitaciones')
                            ->where('operacion.registro_id', $examenId);
                    })
                    ->orWhere('codigo_aula.examen_id', $examenId)
                    ->orWhere('acceso.examen_id', $examenId)
                    ->orWhere('incidencia.examen_id', $examenId)
                    ->orWhere('ingreso.examen_id', $examenId);
            });

        return $consulta->selectRaw(
            "concat('operacion-', operacion.id) as id, operacion.fecha_operacion as ocurrida_en, "
            ."case when operacion.operacion like 'habilitacion.%' then 'HABILITACION' "
            ."when operacion.operacion like 'qr.%' or operacion.operacion like 'examen_aula_codigo.%' then 'CODIGO_QR' "
            ."when operacion.operacion like 'acceso_%' then 'ACCESO_AULA' "
            ."when operacion.operacion = 'puerta.excepcion' then 'INGRESO' "
            ."when operacion.operacion like 'incidencia.%' then 'INCIDENCIA' "
            ."else 'ADMINISTRACION' end as categoria, "
            .'operacion.operacion as suceso, '
            ."coalesce(nullif(trim(concat_ws(' ', estudiante.apellidos, estudiante.nombres)), ''), "
            ."nullif(incidencia.estudiante_texto, ''), 'Sin identificar') as estudiante, "
            .'estudiante.codigo_universitario as codigo, '
            .'coalesce(ingreso.aula_id, incidencia.aula_id, codigo_aula.aula_id, acceso.aula_id) as aula_id, '
            .'aula.nombre as aula, operacion.descripcion as detalle, '
            ."coalesce(acceso.nombre_colaborador, actor.nombre, 'Sistema') as actor",
        );
    }

    /**
     * @return array{
     *     id: string,
     *     ocurrida_en: string,
     *     categoria: string,
     *     suceso: string,
     *     estudiante: string,
     *     codigo: string|null,
     *     aula_id: int|null,
     *     aula: string|null,
     *     detalle: string,
     *     actor: string
     * }
     */
    private function evento(object $fila): array
    {
        $columnas = get_object_vars($fila);
        $suceso = self::texto($columnas['suceso'] ?? null) ?? '';

        return [
            'id' => self::texto($columnas['id'] ?? null) ?? '',
            'ocurrida_en' => self::texto($columnas['ocurrida_en'] ?? null) ?? '',
            'categoria' => self::texto($columnas['categoria'] ?? null) ?? '',
            'suceso' => self::etiquetaOperacion($suceso),
            'estudiante' => self::texto($columnas['estudiante'] ?? null) ?? 'Sin identificar',
            'codigo' => self::texto($columnas['codigo'] ?? null),
            'aula_id' => self::enteroONulo($columnas['aula_id'] ?? null),
            'aula' => self::texto($columnas['aula'] ?? null),
            'detalle' => self::texto($columnas['detalle'] ?? null) ?? '',
            'actor' => self::texto($columnas['actor'] ?? null) ?? 'Sistema',
        ];
    }

    private static function etiquetaOperacion(string $operacion): string
    {
        return match ($operacion) {
            'examen.registrar' => 'Examen creado',
            'examen.modificar' => 'Examen actualizado',
            'examen.eliminar' => 'Examen eliminado',
            'examen.finalizar' => 'Examen finalizado',
            'examen.mover_personal' => 'Personal trasladado',
            'examen.mover_estudiantes' => 'Estudiantes trasladados',
            'habilitacion.habilitar' => 'Estudiantes habilitados',
            'habilitacion.inhabilitar' => 'Estudiantes inhabilitados',
            'habilitacion.repartir' => 'Estudiantes asignados a aulas',
            'habilitacion.sin_foto' => 'No habilitados por no subir su foto',
            'qr.emitir' => 'Códigos QR emitidos',
            'qr.enviar' => 'Códigos QR enviados por correo',
            'aviso.enviar' => 'Aviso enviado por correo',
            'examen_aula_codigo.generar' => 'Código de aula generado',
            'examen_aula_codigo.regenerar' => 'Código de aula regenerado',
            'acceso_codigo_aula.ingreso' => 'Solicitud de acceso de personal',
            'acceso_aula.autorizar' => 'Acceso del personal autorizado',
            'acceso_aula.rechazar' => 'Acceso del personal rechazado',
            'acceso_aula.cortar' => 'Acceso del personal revocado',
            'acceso_aula.trasladar' => 'Personal trasladado de aula',
            'puerta.excepcion' => 'Ingreso autorizado por excepción',
            'incidencia.atender' => 'Incidencia atendida',
            'incidencia.corregir' => 'Incidencia corregida',
            default => $operacion,
        };
    }

    private static function normalizar(string $texto): string
    {
        return strtr(mb_strtolower($texto), [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    private static function texto(mixed $valor): ?string
    {
        return is_string($valor) || is_numeric($valor) ? (string) $valor : null;
    }

    private static function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }

    private static function enteroONulo(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }
}
