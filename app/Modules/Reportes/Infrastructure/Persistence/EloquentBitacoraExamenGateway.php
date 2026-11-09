<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Persistence;

use App\Modules\Reportes\Application\Contracts\BitacoraExamenGateway;
use App\Modules\Reportes\Application\DTOs\ControlDeAula;
use App\Modules\Reportes\Application\DTOs\SucesoExamen;
use App\Modules\Reportes\Domain\Enums\GravedadIncidencia;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * La bitacora de un examen se arma con lo que ya registran los otros
 * modulos: los ingresos, las incidencias (los intentos rechazados son
 * incidencias), los accesos con codigo de aula, el personal de cada aula,
 * el horario del examen y la bitacora de operaciones. Se leen por nombre de
 * tabla.
 */
final class EloquentBitacoraExamenGateway implements BitacoraExamenGateway
{
    /**
     * El nombre de quien opero: el del docente si lo es, si no el de la cuenta.
     */
    private const PERSONA = 'coalesce(%1$s_docente.nombre_completo, %1$s.name)';

    private const MEDIOS = [
        'QR' => 'por código QR',
        'CODIGO' => 'por código SIS',
        'DOCUMENTO' => 'por C.I.',
    ];

    private const INCIDENCIAS = [
        'SUPLANTACION' => 'Suplantación de identidad',
        'MATERIAL_NO_PERMITIDO' => 'Material no permitido',
        'DISPOSITIVO_NO_PERMITIDO' => 'Dispositivo no permitido',
        'INGRESO_REPETIDO' => 'Intento de ingreso repetido',
        'EXPULSION' => 'Expulsión',
        'CAMBIO_AULA' => 'Cambio de aula',
        'OTRA' => 'Incidencia',
    ];

    private const CAUSAS = [
        'NO_INSCRITO' => 'no inscrito en el examen',
        'NO_HABILITADO' => 'no habilitado',
        'CODIGO_INVALIDO' => 'código no válido',
    ];

    /**
     * Las operaciones de la bitacora general que son del examen.
     */
    private const OPERACIONES = [
        'examen.registrar' => 'Examen registrado',
        'examen.modificar' => 'Examen modificado',
        'examen.asignar_auxiliar' => 'Personal de aulas asignado',
        'examen.finalizar' => 'Examen finalizado',
        'habilitacion.habilitar' => 'Estudiantes habilitados',
        'habilitacion.inhabilitar' => 'Estudiantes no habilitados',
        'habilitacion.sin_foto' => 'No habilitados por no subir su foto',
        'habilitacion.repartir' => 'Estudiantes repartidos en aulas',
        'qr.emitir' => 'Códigos QR emitidos',
        'codigo_qr.emitir' => 'Códigos QR emitidos',
        'aviso.enviar' => 'Aviso enviado por correo',
    ];

    private const ACCESOS = [
        'pendiente' => 'pidió entrar',
        'autorizado' => 'autorizado',
        'rechazado' => 'rechazado',
        'cortado' => 'cortado',
    ];

    /**
     * @return list<SucesoExamen>
     */
    public function sucesos(int $examenId): array
    {
        $sucesos = [
            ...$this->momentos($examenId),
            ...$this->ingresos($examenId),
            ...$this->incidencias($examenId),
            ...$this->accesos($examenId),
            ...$this->operaciones($examenId),
            ...$this->operacionesDeAulas($examenId),
            // Al final: con el mismo instante que la finalizacion, el cierre
            // queda despues (el orden es estable).
            ...$this->cierre($examenId),
        ];

        usort(
            $sucesos,
            static fn (SucesoExamen $a, SucesoExamen $b): int => $a->instante <=> $b->instante,
        );

        return $sucesos;
    }

    /**
     * @return list<ControlDeAula>
     */
    public function controlDeAulas(int $examenId): array
    {
        $asignadas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->leftJoin('users as encargado', 'encargado.id', '=', 'asignada.encargado_id')
            ->leftJoin('docentes as encargado_docente', 'encargado_docente.user_id', '=', 'encargado.id')
            ->where('asignada.examen_id', $examenId)
            ->orderBy('aula.nombre')
            ->orderBy('asignada.id')
            ->select(['asignada.id', 'asignada.aula_id', 'asignada.encargado_id', 'aula.nombre as aula'])
            ->selectRaw(sprintf(self::PERSONA, 'encargado').' as encargado')
            ->selectRaw('encargado_docente.id is not null as es_docente')
            ->get();

        if ($asignadas->isEmpty()) {
            return [];
        }

        $ids = $asignadas->map(fn (object $fila): int => (int) $this->texto(get_object_vars($fila)['id'] ?? null))->all();

        /** @var array<int, list<array{nombre: string, rol: string, id: int}>> $apoyos */
        $apoyos = [];
        foreach (DB::table('examen_aula_apoyo as apoyo')
            ->join('users as persona', 'persona.id', '=', 'apoyo.user_id')
            ->leftJoin('docentes as persona_docente', 'persona_docente.user_id', '=', 'persona.id')
            ->whereIn('apoyo.examen_aula_id', $ids)
            ->orderBy('apoyo.id')
            ->select(['apoyo.examen_aula_id', 'apoyo.user_id'])
            ->selectRaw(sprintf(self::PERSONA, 'persona').' as nombre')
            ->selectRaw('persona_docente.id is not null as es_docente')
            ->get() as $fila) {
            $c = get_object_vars($fila);
            $apoyos[(int) $this->texto($c['examen_aula_id'])][] = [
                'nombre' => $this->texto($c['nombre']) ?? '',
                'rol' => (bool) $c['es_docente'] ? 'docente' : 'auxiliar',
                'id' => (int) $this->texto($c['user_id']),
            ];
        }

        /** @var array<int, list<string>> $conCodigo */
        $conCodigo = [];
        foreach (DB::table('accesos_aula as acceso')
            ->whereIn('acceso.examen_aula_id', $ids)
            ->whereIn('acceso.estado', ['AUTORIZADO', 'CORTADO'])
            ->orderBy('acceso.creado_en')
            ->orderBy('acceso.id')
            ->get(['acceso.examen_aula_id', 'acceso.nombre']) as $fila) {
            $c = get_object_vars($fila);
            $nombre = $this->texto($c['nombre']);
            $asignada = (int) $this->texto($c['examen_aula_id']);

            if ($nombre !== null && ! in_array($nombre, $conCodigo[$asignada] ?? [], true)) {
                $conCodigo[$asignada][] = $nombre;
            }
        }

        /** @var array<int, array{cuantos: int, primero: string|null, ultimo: string|null}> $ingresos */
        $ingresos = [];
        foreach (DB::table('ingresos')
            ->where('examen_id', $examenId)
            ->groupBy('aula_id')
            ->select('aula_id')
            ->selectRaw('count(*) as cuantos')
            ->selectRaw("to_char(min(registrado_en), 'HH24:MI') as primero")
            ->selectRaw("to_char(max(registrado_en), 'HH24:MI') as ultimo")
            ->get() as $fila) {
            $c = get_object_vars($fila);
            $ingresos[(int) $this->texto($c['aula_id'])] = [
                'cuantos' => (int) $this->texto($c['cuantos']),
                'primero' => $this->texto($c['primero']),
                'ultimo' => $this->texto($c['ultimo']),
            ];
        }

        $aulas = [];

        foreach ($asignadas as $fila) {
            $c = get_object_vars($fila);
            $id = (int) $this->texto($c['id']);
            $encargadoId = $this->texto($c['encargado_id']);
            $encargado = $this->texto($c['encargado']);
            $personal = $encargado === null
                ? []
                : [['nombre' => $encargado, 'rol' => (bool) $c['es_docente'] ? 'docente' : 'auxiliar']];

            foreach ($apoyos[$id] ?? [] as $apoyo) {
                if ((string) $apoyo['id'] !== $encargadoId) {
                    $personal[] = ['nombre' => $apoyo['nombre'], 'rol' => $apoyo['rol']];
                }
            }

            $deLaPuerta = $ingresos[(int) $this->texto($c['aula_id'])] ?? null;

            $aulas[] = new ControlDeAula(
                aula: $this->texto($c['aula']) ?? '',
                personal: $personal,
                conCodigo: $conCodigo[$id] ?? [],
                ingresos: $deLaPuerta['cuantos'] ?? 0,
                primerIngreso: $deLaPuerta['primero'] ?? null,
                ultimoIngreso: $deLaPuerta['ultimo'] ?? null,
            );
        }

        return $aulas;
    }

    /**
     * El inicio programado, cuando ya llego, y el fin del dia del examen si
     * nadie lo finalizo a mano (la finalizacion manual sale de la bitacora
     * de operaciones).
     *
     * @return list<SucesoExamen>
     */
    private function momentos(int $examenId): array
    {
        $fila = DB::table('examenes')
            ->where('id', $examenId)
            ->first(['fecha', 'hora_inicio', 'duracion_minutos', 'finalizado_en']);

        if ($fila === null) {
            return [];
        }

        $c = get_object_vars($fila);
        $fecha = substr($this->texto($c['fecha']) ?? '', 0, 10);
        $hora = substr($this->texto($c['hora_inicio']) ?? '', 0, 8);
        $minutos = (int) $this->texto($c['duracion_minutos']);
        $inicio = new DateTimeImmutable("{$fecha} {$hora}");
        $ahora = now()->format('Y-m-d H:i:s');
        $sucesos = [];

        if ($inicio->format('Y-m-d H:i:s') <= $ahora) {
            $sucesos[] = new SucesoExamen(
                $inicio->format('Y-m-d H:i:s'),
                'examen',
                'Inicio del examen',
                sprintf('Programado %s – %s · %d min', $inicio->format('H:i'), $inicio->modify("+{$minutos} minutes")->format('H:i'), $minutos),
                null,
                null,
                null,
                null,
            );
        }

        if ($this->texto($c['finalizado_en']) === null && $fecha < substr($ahora, 0, 10)) {
            $sucesos[] = new SucesoExamen(
                "{$fecha} 23:59:59",
                'examen',
                'Fin del día del examen',
                'Sin finalización manual',
                null,
                null,
                null,
                null,
            );
        }

        return $sucesos;
    }

    /**
     * @return list<SucesoExamen>
     */
    private function ingresos(int $examenId): array
    {
        $filas = DB::table('ingresos as ingreso')
            ->join('examenes as examen', 'examen.id', '=', 'ingreso.examen_id')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'ingreso.estudiante_id')
            ->join('aulas as aula', 'aula.id', '=', 'ingreso.aula_id')
            ->leftJoin('habilitaciones as habilitacion', function (JoinClause $union): void {
                $union->on('habilitacion.examen_id', '=', 'ingreso.examen_id')
                    ->on('habilitacion.estudiante_id', '=', 'ingreso.estudiante_id');
            })
            ->leftJoin('aulas as sugerida', 'sugerida.id', '=', 'habilitacion.aula_id')
            ->leftJoin('users as autor', 'autor.id', '=', 'ingreso.registrado_por')
            ->leftJoin('docentes as autor_docente', 'autor_docente.user_id', '=', 'autor.id')
            ->leftJoin('accesos_aula as acceso', 'acceso.id', '=', 'ingreso.acceso_aula_id')
            ->where('ingreso.examen_id', $examenId)
            ->select([
                'ingreso.medio',
                'ingreso.es_excepcion',
                'aula.nombre as aula',
                'sugerida.nombre as sugerida',
                'estudiante.codigo_universitario as codigo',
                'acceso.nombre as acceso',
            ])
            ->selectRaw("to_char(ingreso.registrado_en, 'YYYY-MM-DD HH24:MI:SS') as instante")
            ->selectRaw("trim(estudiante.apellidos || ' ' || estudiante.nombres) as estudiante")
            ->selectRaw(sprintf(self::PERSONA, 'autor').' as autor')
            ->selectRaw('ingreso.registrado_en > (examen.fecha + examen.hora_inicio) as tarde')
            ->get();

        $sucesos = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $c */
            $c = get_object_vars($fila);
            $aula = $this->texto($c['aula']);
            $sugerida = $this->texto($c['sugerida']);

            $sucesos[] = new SucesoExamen(
                instante: $this->texto($c['instante']) ?? '',
                categoria: 'ingreso',
                titulo: 'Ingresó',
                detalle: implode(' · ', array_filter([
                    self::MEDIOS[$this->texto($c['medio']) ?? ''] ?? null,
                    (bool) $c['es_excepcion'] ? 'por excepción' : null,
                    $sugerida !== null && $sugerida !== $aula ? "su aula sugerida era la {$sugerida}" : null,
                    (bool) $c['tarde'] ? 'después del inicio' : null,
                ])),
                aula: $aula,
                estudiante: $this->texto($c['estudiante']),
                codigo: $this->texto($c['codigo']),
                por: $this->texto($c['acceso']) !== null
                    ? $this->texto($c['acceso']).' (código de aula)'
                    : $this->texto($c['autor']),
            );
        }

        return $sucesos;
    }

    /**
     * Las incidencias del examen y los intentos sin examen en sus aulas
     * dentro de su ventana; cada una con su atencion y su correccion.
     *
     * @return list<SucesoExamen>
     */
    private function incidencias(int $examenId): array
    {
        $minutos = config('umss.minutos_apertura', 60);
        $minutos = is_numeric($minutos) ? (int) $minutos : 60;

        $filas = DB::table('incidencias as incidencia')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'incidencia.aula_id')
            ->leftJoin('estudiantes as estudiante', 'estudiante.id', '=', 'incidencia.estudiante_id')
            ->leftJoin('users as autor', 'autor.id', '=', 'incidencia.reportada_por')
            ->leftJoin('docentes as autor_docente', 'autor_docente.user_id', '=', 'autor.id')
            ->leftJoin('users as atendio', 'atendio.id', '=', 'incidencia.atendida_por')
            ->leftJoin('docentes as atendio_docente', 'atendio_docente.user_id', '=', 'atendio.id')
            ->leftJoin('users as corrigio', 'corrigio.id', '=', 'incidencia.corregida_por')
            ->leftJoin('docentes as corrigio_docente', 'corrigio_docente.user_id', '=', 'corrigio.id')
            ->leftJoin('accesos_aula as acceso', 'acceso.id', '=', 'incidencia.acceso_aula_id')
            ->where(function (Builder $donde) use ($examenId, $minutos): void {
                $donde->where('incidencia.examen_id', $examenId)
                    ->orWhere(function (Builder $sinExamen) use ($examenId, $minutos): void {
                        $sinExamen->whereNull('incidencia.examen_id')
                            ->whereExists(function (Builder $aulaDelExamen) use ($examenId, $minutos): void {
                                $aulaDelExamen->from('examen_aula as asignada')
                                    ->join('examenes as examen', 'examen.id', '=', 'asignada.examen_id')
                                    ->whereColumn('asignada.aula_id', 'incidencia.aula_id')
                                    ->where('asignada.examen_id', $examenId)
                                    ->whereRaw(
                                        "incidencia.ocurrida_en >= (examen.fecha + examen.hora_inicio) - (? * interval '1 minute')",
                                        [$minutos],
                                    )
                                    ->whereRaw("incidencia.ocurrida_en <= coalesce(examen.finalizado_en, examen.fecha + interval '1 day')");
                            });
                    });
            })
            ->select([
                'incidencia.tipo',
                'incidencia.gravedad',
                'incidencia.tipo_texto',
                'incidencia.causa',
                'incidencia.detalle',
                'incidencia.estudiante_texto',
                'aula.nombre as aula',
                'estudiante.codigo_universitario as codigo',
                'acceso.nombre as acceso',
            ])
            ->selectRaw("to_char(incidencia.ocurrida_en, 'YYYY-MM-DD HH24:MI:SS') as instante")
            ->selectRaw("to_char(incidencia.atendida_en, 'YYYY-MM-DD HH24:MI:SS') as atendida")
            ->selectRaw("to_char(incidencia.corregida_en, 'YYYY-MM-DD HH24:MI:SS') as corregida")
            ->selectRaw("trim(estudiante.apellidos || ' ' || estudiante.nombres) as estudiante")
            ->selectRaw(sprintf(self::PERSONA, 'autor').' as autor')
            ->selectRaw(sprintf(self::PERSONA, 'atendio').' as atendio')
            ->selectRaw(sprintf(self::PERSONA, 'corrigio').' as corrigio')
            ->get();

        $sucesos = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $c */
            $c = get_object_vars($fila);
            $tipo = $this->texto($c['tipo']) ?? 'OTRA';
            $rechazo = $tipo === 'INTENTO_NO_AUTORIZADO';
            $titulo = $rechazo ? 'Intento rechazado' : ($this->texto($c['tipo_texto']) ?? self::INCIDENCIAS[$tipo] ?? 'Incidencia');
            $aula = $this->texto($c['aula']);
            $estudiante = $this->texto($c['estudiante']) ?? $this->texto($c['estudiante_texto']);
            $codigo = $this->texto($c['codigo']);
            $autor = $this->texto($c['acceso']) !== null
                ? $this->texto($c['acceso']).' (código de aula)'
                : $this->texto($c['autor']);
            $gravedad = GravedadIncidencia::deFila($this->texto($c['gravedad']), $tipo)->value;

            $sucesos[] = new SucesoExamen(
                instante: $this->texto($c['instante']) ?? '',
                categoria: $rechazo ? 'rechazo' : 'incidencia',
                titulo: $titulo,
                detalle: implode(' · ', array_filter([
                    $rechazo ? (self::CAUSAS[$this->texto($c['causa']) ?? ''] ?? null) : null,
                    $this->texto($c['detalle']),
                ])),
                aula: $aula,
                estudiante: $estudiante,
                codigo: $codigo,
                por: $autor,
                gravedad: $gravedad,
            );

            if (($atendida = $this->texto($c['atendida'])) !== null) {
                $sucesos[] = new SucesoExamen($atendida, 'incidencia', "{$titulo}: atendida", '', $aula, $estudiante, $codigo, $this->texto($c['atendio']), $gravedad);
            }

            if (($corregida = $this->texto($c['corregida'])) !== null) {
                $sucesos[] = new SucesoExamen($corregida, 'incidencia', "{$titulo}: corregida", '', $aula, $estudiante, $codigo, $this->texto($c['corrigio']), $gravedad);
            }
        }

        return $sucesos;
    }

    /**
     * Quienes entraron a una puerta con el codigo de aula y que paso con su
     * pedido.
     *
     * @return list<SucesoExamen>
     */
    private function accesos(int $examenId): array
    {
        $filas = DB::table('accesos_aula as acceso')
            ->join('examen_aula as asignada', 'asignada.id', '=', 'acceso.examen_aula_id')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->leftJoin('users as resolvio', 'resolvio.id', '=', 'acceso.resuelto_por')
            ->leftJoin('docentes as resolvio_docente', 'resolvio_docente.user_id', '=', 'resolvio.id')
            ->where('asignada.examen_id', $examenId)
            ->select(['acceso.nombre', 'acceso.estado', 'aula.nombre as aula'])
            ->selectRaw("to_char(acceso.creado_en, 'YYYY-MM-DD HH24:MI:SS') as creado")
            ->selectRaw("to_char(acceso.resuelto_en, 'YYYY-MM-DD HH24:MI:SS') as resuelto")
            ->selectRaw(sprintf(self::PERSONA, 'resolvio').' as resolvio')
            ->get();

        $sucesos = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $c */
            $c = get_object_vars($fila);
            $nombre = $this->texto($c['nombre']) ?? '';
            $aula = $this->texto($c['aula']);

            $sucesos[] = new SucesoExamen(
                $this->texto($c['creado']) ?? '',
                'acceso',
                'Acceso con código de aula',
                "{$nombre} pidió entrar a la puerta",
                $aula,
                null,
                null,
                $nombre,
            );

            // Se guarda en mayusculas (`AUTORIZADO`).
            $estado = strtolower($this->texto($c['estado']) ?? '');
            if (($resuelto = $this->texto($c['resuelto'])) !== null && $estado !== 'pendiente') {
                $sucesos[] = new SucesoExamen(
                    $resuelto,
                    'acceso',
                    'Acceso con código de aula',
                    sprintf('%s: %s', $nombre, self::ACCESOS[$estado] ?? $estado),
                    $aula,
                    null,
                    null,
                    $this->texto($c['resolvio']),
                );
            }
        }

        return $sucesos;
    }

    /**
     * Lo asentado en la bitacora general sobre el examen: su registro, la
     * habilitacion, los codigos QR, los avisos y la finalizacion.
     *
     * @return list<SucesoExamen>
     */
    private function operaciones(int $examenId): array
    {
        $filas = DB::table('bitacora_operaciones as operacion')
            ->leftJoin('users as autor', 'autor.id', '=', 'operacion.usuario_id')
            ->leftJoin('docentes as autor_docente', 'autor_docente.user_id', '=', 'autor.id')
            ->whereIn('operacion.operacion', array_keys(self::OPERACIONES))
            ->whereIn('operacion.tabla_afectada', ['examenes', 'habilitaciones', 'codigos_qr'])
            ->where('operacion.registro_id', $examenId)
            ->select(['operacion.operacion', 'operacion.descripcion', 'operacion.actor'])
            ->selectRaw("to_char(operacion.fecha_operacion, 'YYYY-MM-DD HH24:MI:SS') as instante")
            ->selectRaw(sprintf(self::PERSONA, 'autor').' as autor')
            ->get();

        $sucesos = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $c */
            $c = get_object_vars($fila);

            $sucesos[] = new SucesoExamen(
                $this->texto($c['instante']) ?? '',
                'examen',
                self::OPERACIONES[$this->texto($c['operacion']) ?? ''] ?? 'Examen',
                $this->texto($c['descripcion']) ?? '',
                null,
                null,
                null,
                $this->texto($c['autor']) ?? $this->texto($c['actor']),
            );
        }

        return $sucesos;
    }

    /**
     * Lo hecho en las aulas del examen que asienta la bitacora general: el
     * codigo de aula generado o regenerado, el personal que paso de un aula a
     * otra, los estudiantes trasladados y quien controla con codigo y paso a
     * otra aula.
     *
     * @return list<SucesoExamen>
     */
    private function operacionesDeAulas(int $examenId): array
    {
        $titulos = [
            'codigo_aula.generar' => ['acceso', 'Código de aula generado'],
            'codigo_aula.regenerar' => ['acceso', 'Código de aula regenerado'],
            'acceso_aula.trasladar' => ['acceso', 'Control con código pasó a otra aula'],
            'examen.mover_personal' => ['examen', 'Personal cambiado de aula'],
            'habilitacion.mover_aula' => ['examen', 'Estudiantes cambiados de aula'],
        ];

        $porAula = DB::table('bitacora_operaciones as operacion')
            ->join('examen_aula as asignada', 'asignada.id', '=', 'operacion.registro_id')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->where('operacion.tabla_afectada', 'examen_aula')
            ->whereIn('operacion.operacion', ['codigo_aula.generar', 'codigo_aula.regenerar', 'examen.mover_personal'])
            ->where('asignada.examen_id', $examenId)
            ->select(['operacion.id', 'aula.nombre as aula']);

        $porAcceso = DB::table('bitacora_operaciones as operacion')
            ->join('accesos_aula as acceso', 'acceso.id', '=', 'operacion.registro_id')
            ->join('examen_aula as asignada', 'asignada.id', '=', 'acceso.examen_aula_id')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->where('operacion.tabla_afectada', 'accesos_aula')
            ->where('operacion.operacion', 'acceso_aula.trasladar')
            ->where('asignada.examen_id', $examenId)
            ->select(['operacion.id', 'aula.nombre as aula']);

        // El traslado de estudiantes se asienta sobre el examen: el detalle
        // ya dice de que aula a cual.
        $delExamen = DB::table('bitacora_operaciones as operacion')
            ->where('operacion.tabla_afectada', 'examenes')
            ->where('operacion.operacion', 'habilitacion.mover_aula')
            ->where('operacion.registro_id', $examenId)
            ->select(['operacion.id'])
            ->selectRaw('null as aula');

        $filas = DB::query()
            ->fromSub($porAula->unionAll($porAcceso)->unionAll($delExamen), 'propia')
            ->join('bitacora_operaciones as operacion', 'operacion.id', '=', 'propia.id')
            ->leftJoin('users as autor', 'autor.id', '=', 'operacion.usuario_id')
            ->leftJoin('docentes as autor_docente', 'autor_docente.user_id', '=', 'autor.id')
            ->select(['propia.aula', 'operacion.operacion', 'operacion.descripcion', 'operacion.actor'])
            ->selectRaw("to_char(operacion.fecha_operacion, 'YYYY-MM-DD HH24:MI:SS') as instante")
            ->selectRaw(sprintf(self::PERSONA, 'autor').' as autor')
            ->get();

        $sucesos = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $c */
            $c = get_object_vars($fila);
            [$categoria, $titulo] = $titulos[$this->texto($c['operacion']) ?? ''] ?? ['examen', 'Examen'];

            $sucesos[] = new SucesoExamen(
                $this->texto($c['instante']) ?? '',
                $categoria,
                $titulo,
                $this->texto($c['descripcion']) ?? '',
                $this->texto($c['aula']),
                null,
                null,
                $this->texto($c['autor']) ?? $this->texto($c['actor']),
            );
        }

        return $sucesos;
    }

    /**
     * El cierre, cuando el examen ya termino (se finalizo a mano o paso su
     * dia): cuantos ingresaron de los habilitados, cuantos no se presentaron
     * y cuantas incidencias hubo; despues, cada habilitado que no se
     * presento, con el aula que tenia, para responder un reclamo buscando al
     * estudiante.
     *
     * @return list<SucesoExamen>
     */
    private function cierre(int $examenId): array
    {
        $fila = DB::table('examenes')->where('id', $examenId)->first(['fecha', 'finalizado_en']);

        if ($fila === null) {
            return [];
        }

        $c = get_object_vars($fila);
        $fecha = substr($this->texto($c['fecha']) ?? '', 0, 10);
        $finalizado = $this->texto($c['finalizado_en']);
        $ahora = now()->format('Y-m-d H:i:s');

        if ($finalizado === null && $fecha >= substr($ahora, 0, 10)) {
            return [];
        }

        $instante = $finalizado !== null ? substr($finalizado, 0, 19) : "{$fecha} 23:59:59";

        $ausentes = DB::table('habilitaciones as habilitacion')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'habilitacion.estudiante_id')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'habilitacion.aula_id')
            ->where('habilitacion.examen_id', $examenId)
            ->where('habilitacion.habilitado', true)
            ->whereNotExists(function (Builder $ingreso): void {
                $ingreso->selectRaw('1')
                    ->from('ingresos')
                    ->whereColumn('ingresos.examen_id', 'habilitacion.examen_id')
                    ->whereColumn('ingresos.estudiante_id', 'habilitacion.estudiante_id');
            })
            ->orderBy('estudiante.apellidos')
            ->orderBy('estudiante.nombres')
            ->select(['estudiante.codigo_universitario as codigo', 'aula.nombre as aula'])
            ->selectRaw("trim(estudiante.apellidos || ' ' || estudiante.nombres) as estudiante")
            ->get();

        $habilitados = DB::table('habilitaciones')->where('examen_id', $examenId)->where('habilitado', true)->count();
        $ingresaron = DB::table('ingresos')->where('examen_id', $examenId)->distinct()->count('estudiante_id');
        $incidencias = DB::table('incidencias')->where('examen_id', $examenId)->where('tipo', '<>', 'INTENTO_NO_AUTORIZADO')->count();
        $rechazos = DB::table('incidencias')->where('examen_id', $examenId)->where('tipo', 'INTENTO_NO_AUTORIZADO')->count();
        $plural = static fn (int $n, string $uno, string $varios): string => $n.' '.($n === 1 ? $uno : $varios);

        $sucesos = [new SucesoExamen(
            $instante,
            'examen',
            'Cierre del examen',
            implode(' · ', [
                sprintf('Ingresaron %d de %d habilitados', $ingresaron, $habilitados),
                $plural($ausentes->count(), 'no se presentó', 'no se presentaron'),
                $plural($incidencias, 'incidencia', 'incidencias'),
                $plural($rechazos, 'intento rechazado', 'intentos rechazados'),
            ]),
            null,
            null,
            null,
            null,
        )];

        foreach ($ausentes as $ausente) {
            /** @var array<string, mixed> $a */
            $a = get_object_vars($ausente);
            $sucesos[] = new SucesoExamen(
                $instante,
                'ausente',
                'No se presentó',
                'Habilitado',
                $this->texto($a['aula']),
                $this->texto($a['estudiante']),
                $this->texto($a['codigo']),
                null,
            );
        }

        return $sucesos;
    }

    private function texto(mixed $valor): ?string
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
