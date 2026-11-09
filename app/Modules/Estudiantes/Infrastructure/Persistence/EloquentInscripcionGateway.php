<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\DTOs\AltaDeInscritoData;
use App\Modules\Estudiantes\Application\DTOs\BajaDeInscritoData;
use App\Modules\Estudiantes\Application\DTOs\FilaInscritoData;
use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Application\DTOs\GrupoDeCargaData;
use App\Modules\Estudiantes\Application\DTOs\PaginaData;
use App\Modules\Estudiantes\Application\DTOs\RechazoCargaData;
use App\Modules\Estudiantes\Application\DTOs\RegistroCargaData;
use App\Modules\Estudiantes\Domain\Enums\EstadoConflicto;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Rules\DatosDeInscrito;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Una inscripcion con `baja_en` sigue en la tabla (es historia), pero
 * ninguna lectura la cuenta.
 */
final class EloquentInscripcionGateway implements InscripcionGateway
{
    use ConsultasDePadron;

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function grupo(int $grupoId): ?GrupoDeCargaData
    {
        $fila = DB::table('grupos as g')
            ->join('periodos as p', 'p.id', '=', 'g.periodo_id')
            ->join('asignaturas as a', 'a.id', '=', 'g.asignatura_id')
            ->where('g.id', $grupoId)
            ->first([
                'g.id',
                'g.periodo_id',
                'g.facultad_id',
                'g.codigo',
                'a.codigo as asignatura_codigo',
                'p.fecha_inicio',
                'p.fecha_fin',
            ]);

        if ($fila === null) {
            return null;
        }

        $fila = get_object_vars($fila);

        $hoy = now()->toDateString();
        $inicio = $this->texto(($fila['fecha_inicio'] ?? null));
        $fin = $this->texto(($fila['fecha_fin'] ?? null));

        return new GrupoDeCargaData(
            id: $this->entero(($fila['id'] ?? null)),
            periodoId: $this->entero(($fila['periodo_id'] ?? null)),
            facultadId: $this->entero(($fila['facultad_id'] ?? null)),
            periodoVigente: $inicio !== null
                && $fin !== null
                && substr($inicio, 0, 10) <= $hoy
                && substr($fin, 0, 10) >= $hoy,
            codigo: (string) $this->texto(($fila['codigo'] ?? null)),
            asignaturaCodigo: (string) $this->texto(($fila['asignatura_codigo'] ?? null)),
        );
    }

    public function facultadPorClave(string $clave): ?int
    {
        return $this->enteroONulo(
            DB::table('facultades')
                ->whereRaw('lower(clave) = ?', [mb_strtolower(trim($clave))])
                ->value('id')
        );
    }

    /**
     * @return array<string, int>
     */
    public function gruposVigentesDeFacultad(int $facultadId): array
    {
        $consulta = DB::table('grupos as g')
            ->join('asignaturas as a', 'a.id', '=', 'g.asignatura_id')
            ->join('periodos as p', 'p.id', '=', 'g.periodo_id')
            ->where('g.facultad_id', $facultadId);

        $this->vigente($consulta, 'p');

        // Del periodo mas antiguo al mas reciente: el reciente pisa.
        $filas = $consulta
            ->orderBy('p.anio')
            ->orderBy('p.numero')
            ->get(['g.id', 'g.codigo as grupo', 'a.codigo as asignatura']);

        $grupos = [];

        foreach ($filas as $fila) {
            $clave = mb_strtoupper($this->texto($fila->asignatura).'|'.$this->texto($fila->grupo));

            $grupos[$clave] = $this->entero($fila->id);
        }

        // Una hoja de calculo lee el grupo «07» como 7: tambien se lo
        // encuentra sin los ceros, si no choca con otro grupo.
        foreach ($grupos as $clave => $id) {
            [$asignatura, $grupo] = explode('|', $clave, 2) + [1 => ''];
            $sinCeros = $asignatura.'|'.ltrim($grupo, '0');

            if (! isset($grupos[$sinCeros])) {
                $grupos[$sinCeros] = $id;
            }
        }

        return $grupos;
    }

    /**
     * @return array<string, int>
     */
    public function carrerasDeFacultad(int $facultadId): array
    {
        $carreras = [];

        // Por codigo y por nombre: la plantilla acepta cualquiera de los dos.
        foreach (DB::table('carreras')->where('facultad_id', $facultadId)->get(['id', 'codigo', 'nombre']) as $fila) {
            $id = $this->entero($fila->id);
            $carreras[DatosDeInscrito::clave((string) $this->texto($fila->codigo))] = $id;
            $carreras[DatosDeInscrito::clave((string) $this->texto($fila->nombre))] = $id;
        }

        return $carreras;
    }

    /**
     * @param  list<int>  $estudianteIds
     * @param  list<int>  $grupoIds
     * @return array<string, true>
     */
    public function existentes(array $estudianteIds, array $grupoIds): array
    {
        $existentes = [];

        if ($grupoIds === []) {
            return $existentes;
        }

        foreach (array_chunk($estudianteIds, 1000) as $lote) {
            $filas = DB::table('inscripciones')
                ->whereIn('estudiante_id', $lote)
                ->whereIn('grupo_id', $grupoIds)
                ->whereNull('baja_en')
                ->get(['estudiante_id', 'grupo_id']);

            foreach ($filas as $fila) {
                $existentes[$this->entero($fila->estudiante_id).'|'.$this->entero($fila->grupo_id)] = true;
            }
        }

        return $existentes;
    }

    public function registrar(RegistroCargaData $carga): int
    {
        return DB::transaction(function () use ($carga): int {
            $ahora = now();
            $resultado = $carga->resultado;
            $esAdministracion = $carga->via === OrigenEstudiante::Administracion;

            $cargaId = DB::table('cargas_inscritos')->insertGetId([
                'alcance' => $carga->alcance->value,
                'grupo_id' => $carga->grupoId,
                'facultad_id' => $carga->facultadId,
                'periodo_id' => $carga->periodoId,
                'archivo' => $resultado->archivo,
                'filas' => $resultado->filas,
                'nuevos' => $resultado->nuevos,
                'reutilizados' => $resultado->reutilizados,
                'ya_inscritos' => $resultado->yaInscritos,
                'rechazados' => count($resultado->rechazos),
                'conflictos' => count($resultado->conflictos),
                'rechazos' => json_encode(
                    array_map(
                        static fn (RechazoCargaData $rechazo): array => [
                            'fila' => $rechazo->fila,
                            'motivo' => $rechazo->motivo,
                        ],
                        $resultado->rechazos,
                    ),
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
                'cargada_por' => $carga->usuarioId,
                'created_at' => $ahora,
            ]);

            foreach (array_chunk($carga->nuevos, 500) as $lote) {
                DB::table('estudiantes')->insertOrIgnore(array_map(
                    static fn (FilaInscritoData $fila): array => [
                        'codigo_universitario' => $fila->codigoUniversitario,
                        'documento_identidad' => $fila->documentoIdentidad,
                        'nombres' => $fila->nombres,
                        'apellidos' => $fila->apellidos,
                        'carrera_id' => $fila->carreraId,
                        'facultad_id' => $fila->facultadId,
                        'origen' => $carga->via->value,
                        'verificado' => $esAdministracion,
                        'activo' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ],
                    $lote,
                ));
            }

            foreach (array_chunk($carga->porVerificar, 1000) as $lote) {
                DB::table('estudiantes')
                    ->whereIn('codigo_universitario', $lote)
                    ->update(['verificado' => true, 'updated_at' => $ahora]);
            }

            $ids = $this->idsPorCodigo([...$carga->inscripciones, ...$carga->conflictos]);

            $inscripciones = [];

            foreach ($carga->inscripciones as $fila) {
                $estudianteId = $ids[$fila->codigoUniversitario] ?? null;

                if ($estudianteId === null) {
                    continue;
                }

                $inscripciones[] = [
                    'estudiante_id' => $estudianteId,
                    'grupo_id' => $fila->grupoId,
                    'via' => $carga->via->value,
                    'cargada_por' => $carga->usuarioId,
                    'carga_id' => $cargaId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            foreach (array_chunk($inscripciones, 500) as $lote) {
                DB::table('inscripciones')->insertOrIgnore($lote);
            }

            $this->reactivar($inscripciones, $carga->via, $carga->usuarioId, $cargaId, $ahora);

            $this->abrirConflictos($carga, $cargaId, $ids);

            // La del docente es la lista de su grupo; la de la administración,
            // las inscripciones de una facultad.
            $this->bitacora->registrar(
                $carga->usuarioId,
                $carga->via === OrigenEstudiante::Docente ? 'inscritos.cargar' : 'inscripciones.cargar',
                'cargas_inscritos',
                $cargaId,
                sprintf(
                    'Archivo %s: %d filas, %d nuevos, %d reutilizados, %d ya inscritos, %d rechazados, %d en conflicto.',
                    $resultado->archivo,
                    $resultado->filas,
                    $resultado->nuevos,
                    $resultado->reutilizados,
                    $resultado->yaInscritos,
                    count($resultado->rechazos),
                    count($resultado->conflictos),
                ),
            );

            return $cargaId;
        }, 3);
    }

    public function inscritosDeGrupo(int $grupoId, FiltroListaData $filtro): PaginaData
    {
        $consulta = DB::table('inscripciones as i')
            ->join('estudiantes as e', 'e.id', '=', 'i.estudiante_id')
            ->where('i.grupo_id', $grupoId)
            ->whereNull('i.baja_en');

        $this->buscar($consulta, $filtro->buscar, [
            'e.apellidos',
            'e.nombres',
            'e.codigo_universitario',
            'e.documento_identidad',
        ]);

        $total = (clone $consulta)->count();

        $filas = $consulta
            ->orderByRaw("translate(lower(e.apellidos), 'áéíóúüñ', 'aeiouun')")
            ->orderByRaw("translate(lower(e.nombres), 'áéíóúüñ', 'aeiouun')")
            ->orderBy('e.id')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get([
                'e.id',
                'e.codigo_universitario',
                'e.documento_identidad',
                'e.nombres',
                'e.apellidos',
                'i.via',
                'e.foto',
                'e.foto_subida_en',
                'e.foto_habilitada_hasta',
            ]);

        $datos = [];

        foreach ($filas as $fila) {
            $via = OrigenEstudiante::tryFrom((string) $this->texto($fila->via));

            $datos[] = [
                'id' => $this->entero($fila->id),
                'codigo' => $this->texto($fila->codigo_universitario),
                'nombre' => $this->nombreCompleto($fila->apellidos, $fila->nombres),
                // Por separado, para corregirlos.
                'nombres' => $this->texto($fila->nombres),
                'apellidos' => $this->texto($fila->apellidos),
                'documento' => $this->texto($fila->documento_identidad),
                'origen' => ($via ?? OrigenEstudiante::Administracion)->etiqueta(),
                // Lo que el docente necesita para habilitar un cambio de foto.
                'foto' => [
                    'subida_en' => $this->texto($fila->foto) === null
                        ? null
                        : substr((string) $this->texto($fila->foto_subida_en), 0, 10),
                    'habilitada_hasta' => $this->texto($fila->foto_habilitada_hasta) === null
                        ? null
                        : substr((string) $this->texto($fila->foto_habilitada_hasta), 0, 10),
                ],
            ];
        }

        return new PaginaData($datos, $total, $filtro->pagina, $filtro->porPagina);
    }

    /**
     * @return list<array{codigo: string, nombre: string, documento: string, origen: string}>
     */
    public function listaDeGrupo(int $grupoId): array
    {
        $filas = DB::table('inscripciones as i')
            ->join('estudiantes as e', 'e.id', '=', 'i.estudiante_id')
            ->where('i.grupo_id', $grupoId)
            ->whereNull('i.baja_en')
            ->orderByRaw("translate(lower(e.apellidos), 'áéíóúüñ', 'aeiouun')")
            ->orderByRaw("translate(lower(e.nombres), 'áéíóúüñ', 'aeiouun')")
            ->orderBy('e.id')
            ->get([
                'e.codigo_universitario',
                'e.documento_identidad',
                'e.nombres',
                'e.apellidos',
                'i.via',
            ]);

        $lista = [];

        foreach ($filas as $fila) {
            $via = OrigenEstudiante::tryFrom((string) $this->texto($fila->via));

            $lista[] = [
                'codigo' => (string) $this->texto($fila->codigo_universitario),
                'nombre' => $this->nombreCompleto($fila->apellidos, $fila->nombres),
                'documento' => (string) $this->texto($fila->documento_identidad),
                'origen' => ($via ?? OrigenEstudiante::Administracion)->etiqueta(),
            ];
        }

        return $lista;
    }

    public function agregar(AltaDeInscritoData $alta): bool
    {
        return DB::transaction(function () use ($alta): bool {
            $ahora = now();
            $estudianteId = $alta->estudianteId;
            $nuevo = $estudianteId === null;

            if ($estudianteId === null) {
                $estudianteId = $this->entero(DB::table('estudiantes')->insertGetId([
                    'codigo_universitario' => $alta->codigoUniversitario,
                    'documento_identidad' => $alta->documentoIdentidad,
                    'nombres' => $alta->nombres,
                    'apellidos' => $alta->apellidos,
                    'carrera_id' => null,
                    'facultad_id' => $alta->facultadId,
                    'origen' => OrigenEstudiante::Docente->value,
                    'verificado' => false,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]));
            }

            $grupo = $this->grupo($alta->grupoId);
            $enGrupo = $grupo === null ? '' : sprintf(' en el grupo %s de %s', $grupo->codigo, $grupo->asignaturaCodigo);

            // Datos distintos a los del padron: no se tocan. Queda el
            // conflicto y la inscripcion espera a la administracion.
            if ($alta->conflicto !== null) {
                $conflictoId = $this->abrirConflicto($alta, $estudianteId, $ahora);

                $this->bitacora->registrar(
                    $alta->usuarioId,
                    'inscritos.agregar',
                    'conflictos_padron',
                    $conflictoId,
                    sprintf(
                        'Estudiante %s agregado%s: datos distintos a los del padrón, inscripción pendiente.',
                        $alta->codigoUniversitario,
                        $enGrupo,
                    ),
                );

                return false;
            }

            $reactivada = $this->reactivar(
                [['estudiante_id' => $estudianteId, 'grupo_id' => $alta->grupoId]],
                OrigenEstudiante::Docente,
                $alta->usuarioId,
                null,
                $ahora,
            ) > 0;

            if (! $reactivada) {
                DB::table('inscripciones')->insertOrIgnore([
                    'estudiante_id' => $estudianteId,
                    'grupo_id' => $alta->grupoId,
                    'via' => OrigenEstudiante::Docente->value,
                    'cargada_por' => $alta->usuarioId,
                    'carga_id' => null,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }

            $this->bitacora->registrar(
                $alta->usuarioId,
                'inscritos.agregar',
                'inscripciones',
                $this->enteroONulo(
                    DB::table('inscripciones')
                        ->where('estudiante_id', $estudianteId)
                        ->where('grupo_id', $alta->grupoId)
                        ->value('id')
                ),
                sprintf(
                    'Estudiante %s %s%s%s.',
                    $alta->codigoUniversitario,
                    $reactivada ? 'reinscrito' : 'agregado',
                    $enGrupo,
                    $nuevo ? '; nuevo en el padrón' : '',
                ),
            );

            return $reactivada;
        }, 3);
    }

    public function tieneIngresoEnGrupo(int $estudianteId, int $grupoId): bool
    {
        return DB::table('ingresos as ingreso')
            ->join('examen_grupo as incluido', 'incluido.examen_id', '=', 'ingreso.examen_id')
            ->where('incluido.grupo_id', $grupoId)
            ->where('ingreso.estudiante_id', $estudianteId)
            ->exists();
    }

    public function darDeBaja(BajaDeInscritoData $baja): void
    {
        DB::transaction(function () use ($baja): void {
            $ahora = now();

            $inscripcionId = $this->enteroONulo(
                DB::table('inscripciones')
                    ->where('estudiante_id', $baja->estudianteId)
                    ->where('grupo_id', $baja->grupoId)
                    ->whereNull('baja_en')
                    ->lockForUpdate()
                    ->value('id')
            );

            if ($inscripcionId === null) {
                return;
            }

            DB::table('inscripciones')->where('id', $inscripcionId)->update([
                'baja_en' => $ahora,
                'baja_por' => $baja->usuarioId,
                'motivo_baja' => $baja->motivo->value,
                'detalle_baja' => $baja->detalle,
                'updated_at' => $ahora,
            ]);

            // Como al sacar un grupo de un examen: quien ya no esta inscrito
            // en ningun grupo del examen deja de tener habilitacion y
            // codigo QR en el.
            foreach (['habilitaciones', 'codigos_qr'] as $tabla) {
                DB::table($tabla)
                    ->where($tabla.'.estudiante_id', $baja->estudianteId)
                    ->whereIn($tabla.'.examen_id', function (Builder $examenes) use ($baja): void {
                        $examenes->select('examen_id')
                            ->from('examen_grupo')
                            ->where('grupo_id', $baja->grupoId);
                    })
                    ->whereNotExists(function (Builder $inscrito) use ($tabla): void {
                        $inscrito->selectRaw('1')
                            ->from('examen_grupo as incluido')
                            ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
                            ->whereColumn('incluido.examen_id', $tabla.'.examen_id')
                            ->whereColumn('inscripcion.estudiante_id', $tabla.'.estudiante_id')
                            ->whereNull('inscripcion.baja_en');
                    })
                    ->delete();
            }

            $grupo = $this->grupo($baja->grupoId);
            $codigo = $this->texto(
                DB::table('estudiantes')->where('id', $baja->estudianteId)->value('codigo_universitario')
            );

            $this->bitacora->registrar(
                $baja->usuarioId,
                'inscritos.quitar',
                'inscripciones',
                $inscripcionId,
                sprintf(
                    'Estudiante %s quitado%s. Motivo: %s.',
                    (string) $codigo,
                    $grupo === null ? '' : sprintf(' del grupo %s de %s', $grupo->codigo, $grupo->asignaturaCodigo),
                    $baja->detalle === null || $baja->detalle === ''
                        ? $baja->motivo->etiqueta()
                        : $baja->motivo->etiqueta().' ('.$baja->detalle.')',
                ),
            );
        }, 3);
    }

    /**
     * Vuelve a dar de alta las inscripciones dadas de baja entre esas:
     * la carga o el docente que las trae de nuevo pasan a ser su origen.
     * Devuelve cuantas reactivo.
     *
     * @param  list<array<string, mixed>>  $inscripciones  filas con `estudiante_id` y `grupo_id`
     */
    private function reactivar(
        array $inscripciones,
        OrigenEstudiante $via,
        int $usuarioId,
        ?int $cargaId,
        DateTimeInterface $ahora,
    ): int {
        $porGrupo = [];

        foreach ($inscripciones as $inscripcion) {
            $porGrupo[$this->entero($inscripcion['grupo_id'] ?? null)][] = $this->entero($inscripcion['estudiante_id'] ?? null);
        }

        $reactivadas = 0;

        foreach ($porGrupo as $grupoId => $estudianteIds) {
            foreach (array_chunk($estudianteIds, 1000) as $lote) {
                $reactivadas += DB::table('inscripciones')
                    ->where('grupo_id', $grupoId)
                    ->whereIn('estudiante_id', $lote)
                    ->whereNotNull('baja_en')
                    ->update([
                        'baja_en' => null,
                        'baja_por' => null,
                        'motivo_baja' => null,
                        'detalle_baja' => null,
                        'via' => $via->value,
                        'cargada_por' => $usuarioId,
                        'carga_id' => $cargaId,
                        'updated_at' => $ahora,
                    ]);
            }
        }

        return $reactivadas;
    }

    /**
     * El conflicto del estudiante agregado suelto; si ya hay uno pendiente
     * con los mismos datos para el grupo, ese.
     */
    private function abrirConflicto(AltaDeInscritoData $alta, int $estudianteId, DateTimeInterface $ahora): int
    {
        $abierto = DB::table('conflictos_padron')
            ->where('estado', EstadoConflicto::Pendiente->value)
            ->where('estudiante_id', $estudianteId)
            ->where('grupo_id', $alta->grupoId)
            ->where('documento_nuevo', $alta->documentoIdentidad)
            ->where('nombres_nuevos', $alta->nombres)
            ->where('apellidos_nuevos', $alta->apellidos)
            ->value('id');

        if ($abierto !== null) {
            return $this->entero($abierto);
        }

        return $this->entero(DB::table('conflictos_padron')->insertGetId([
            'estudiante_id' => $estudianteId,
            'grupo_id' => $alta->grupoId,
            'carga_id' => null,
            'fila' => null,
            'tipo' => $alta->conflicto?->value,
            'documento_nuevo' => $alta->documentoIdentidad,
            'nombres_nuevos' => $alta->nombres,
            'apellidos_nuevos' => $alta->apellidos,
            'via' => OrigenEstudiante::Docente->value,
            'estado' => EstadoConflicto::Pendiente->value,
            'reportado_por' => $alta->usuarioId,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]));
    }

    /**
     * @param  list<FilaInscritoData>  $filas
     * @return array<string, int>
     */
    private function idsPorCodigo(array $filas): array
    {
        $codigos = [];

        foreach ($filas as $fila) {
            $codigos[$fila->codigoUniversitario] = true;
        }

        $ids = [];

        foreach (array_chunk(array_map(strval(...), array_keys($codigos)), 1000) as $lote) {
            $guardados = DB::table('estudiantes')
                ->whereIn('codigo_universitario', $lote)
                ->get(['id', 'codigo_universitario']);

            foreach ($guardados as $guardado) {
                $ids[(string) $this->texto($guardado->codigo_universitario)] = $this->entero($guardado->id);
            }
        }

        return $ids;
    }

    /**
     * Deja en espera las filas que no coinciden con el padron. Volver a
     * subir el mismo archivo no abre dos veces el mismo conflicto.
     *
     * @param  array<string, int>  $ids
     */
    private function abrirConflictos(RegistroCargaData $carga, int $cargaId, array $ids): void
    {
        if ($carga->conflictos === []) {
            return;
        }

        $estudianteIds = [];

        foreach ($carga->conflictos as $fila) {
            if (isset($ids[$fila->codigoUniversitario])) {
                $estudianteIds[$ids[$fila->codigoUniversitario]] = true;
            }
        }

        $abiertos = [];

        foreach (array_chunk(array_keys($estudianteIds), 1000) as $lote) {
            $pendientes = DB::table('conflictos_padron')
                ->where('estado', EstadoConflicto::Pendiente->value)
                ->whereIn('estudiante_id', $lote)
                ->get(['estudiante_id', 'grupo_id', 'documento_nuevo', 'nombres_nuevos', 'apellidos_nuevos']);

            foreach ($pendientes as $pendiente) {
                $abiertos[implode('|', [
                    $this->entero($pendiente->estudiante_id),
                    $this->entero($pendiente->grupo_id),
                    $this->texto($pendiente->documento_nuevo),
                    $this->texto($pendiente->nombres_nuevos),
                    $this->texto($pendiente->apellidos_nuevos),
                ])] = true;
            }
        }

        $ahora = now();
        $nuevos = [];

        foreach ($carga->conflictos as $fila) {
            $estudianteId = $ids[$fila->codigoUniversitario] ?? null;

            if ($estudianteId === null || $fila->conflicto === null) {
                continue;
            }

            $clave = implode('|', [
                $estudianteId,
                $fila->grupoId,
                $fila->documentoIdentidad,
                $fila->nombres,
                $fila->apellidos,
            ]);

            if (isset($abiertos[$clave])) {
                continue;
            }

            $abiertos[$clave] = true;

            $nuevos[] = [
                'estudiante_id' => $estudianteId,
                'grupo_id' => $fila->grupoId,
                'carga_id' => $cargaId,
                'fila' => $fila->fila,
                'tipo' => $fila->conflicto->value,
                'documento_nuevo' => $fila->documentoIdentidad,
                'nombres_nuevos' => $fila->nombres,
                'apellidos_nuevos' => $fila->apellidos,
                'via' => $carga->via->value,
                'estado' => EstadoConflicto::Pendiente->value,
                'reportado_por' => $carga->usuarioId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($nuevos, 500) as $lote) {
            DB::table('conflictos_padron')->insert($lote);
        }
    }
}
