<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\DTOs\AulaDetalleData;
use App\Modules\Examenes\Application\DTOs\AvanceData;
use App\Modules\Examenes\Application\DTOs\ExamenDetalleData;
use App\Modules\Examenes\Application\DTOs\ExamenResumenData;
use App\Modules\Examenes\Application\DTOs\GrupoDeExamenData;
use App\Modules\Examenes\Application\DTOs\NormaMarcadaData;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Examenes\Domain\Rules\AvanceDeExamen;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Todo lo que el examen muestra de otros modulos (asignatura, grupos,
 * docentes, aulas, inscritos, habilitaciones) se lee por nombre de tabla.
 */
final class EloquentConsultaExamenGateway implements ConsultaExamenGateway
{
    use ConvierteFilas;

    public function listar(int $usuarioId, ?array $periodoIds): array
    {
        $consulta = $this->base()
            ->where(function (Builder $visibles) use ($usuarioId): void {
                $this->delDocente($visibles, 'examen', $usuarioId);
            })
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id');

        if ($periodoIds !== null) {
            $consulta->whereIn('examen.periodo_id', $periodoIds);
        }

        $resumenes = [];

        foreach ($this->armar($consulta, $usuarioId) as $detalle) {
            $resumenes[] = $detalle->resumen;
        }

        return $resumenes;
    }

    public function detalle(int $examenId, int $usuarioId): ?ExamenDetalleData
    {
        return $this->armar($this->base()->where('examen.id', $examenId), $usuarioId)[0] ?? null;
    }

    public function gruposDeAsignatura(int $asignaturaId, array $periodoIds, int $usuarioId): array
    {
        if ($periodoIds === []) {
            return [];
        }

        $grupos = $this->grupos(
            DB::table('grupos as grupo')
                ->where('grupo.asignatura_id', $asignaturaId)
                ->whereIn('grupo.periodo_id', $periodoIds),
            $usuarioId,
        );

        usort(
            $grupos,
            static fn (GrupoDeExamenData $a, GrupoDeExamenData $b): int => strnatcasecmp($a->codigo, $b->codigo),
        );

        return $grupos;
    }

    public function gruposConExamenDeTipo(array $grupoIds, string $tipo, ?int $exceptoExamenId): array
    {
        if ($grupoIds === []) {
            return [];
        }

        $consulta = DB::table('examen_grupo as incluido')
            ->join('examenes as examen', 'examen.id', '=', 'incluido.examen_id')
            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
            ->whereIn('incluido.grupo_id', $grupoIds)
            ->where('examen.tipo', $tipo)
            ->orderBy('grupo.codigo')
            ->distinct();

        if ($exceptoExamenId !== null) {
            $consulta->where('examen.id', '<>', $exceptoExamenId);
        }

        $codigos = [];

        foreach ($consulta->pluck('grupo.codigo') as $codigo) {
            $codigos[] = $this->cadena($codigo);
        }

        return $codigos;
    }

    public function aulasSugeridas(array $grupoIds): array
    {
        if ($grupoIds === []) {
            return [];
        }

        $ids = DB::table('horarios as horario')
            ->join('aulas as aula', 'aula.id', '=', 'horario.aula_id')
            ->whereIn('horario.grupo_id', $grupoIds)
            ->whereNotNull('aula.edificio_id')
            ->distinct()
            ->orderBy('aula.id')
            ->pluck('aula.id');

        $sugeridas = [];

        foreach ($ids as $id) {
            $sugeridas[] = $this->entero($id);
        }

        return $sugeridas;
    }

    public function aulasCompartidas(
        string $fecha,
        string $horaInicio,
        int $duracionMinutos,
        ?int $exceptoExamenId,
    ): array {
        $inicio = $this->minutos($horaInicio);
        $fin = $inicio + $duracionMinutos;

        $consulta = DB::table('examen_aula as asignada')
            ->join('examenes as examen', 'examen.id', '=', 'asignada.examen_id')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->whereDate('examen.fecha', $fecha)
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->select([
                'asignada.aula_id',
                'examen.tipo',
                'examen.hora_inicio',
                'examen.duracion_minutos',
                'asignatura.nombre as asignatura',
            ]);

        if ($exceptoExamenId !== null) {
            $consulta->where('examen.id', '<>', $exceptoExamenId);
        }

        $compartidas = [];

        foreach ($consulta->get() as $fila) {
            $columnas = $this->columnas($fila);
            $inicioOtro = $this->minutos($this->hora($columnas['hora_inicio'] ?? null));
            $finOtro = $inicioOtro + $this->entero($columnas['duracion_minutos'] ?? null);

            // Se solapan si cada uno empieza antes de que el otro termine.
            if ($inicio < $finOtro && $inicioOtro < $fin) {
                $compartidas[$this->entero($columnas['aula_id'] ?? null)][] =
                    $this->cadena($columnas['asignatura'] ?? null)
                    .' · '.$this->tipoTexto($this->cadena($columnas['tipo'] ?? null));
            }
        }

        return $compartidas;
    }

    public function periodo(string $idOCodigo): ?array
    {
        $consulta = DB::table('periodos');

        if (preg_match('/^\d+$/', $idOCodigo) === 1) {
            $consulta->where('id', (int) $idOCodigo);
        } else {
            $consulta->where('codigo', $idOCodigo);
        }

        $fila = $consulta->first(['id', 'codigo']);

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return [
            'id' => $this->entero($columnas['id'] ?? null),
            'codigo' => $this->cadena($columnas['codigo'] ?? null),
        ];
    }

    public function hoy(): string
    {
        return now()->toDateString();
    }

    public function horaServidor(): string
    {
        return now()->toIso8601String();
    }

    private function base(): Builder
    {
        return DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->join('usuarios as creador', 'creador.id', '=', 'examen.creado_por')
            ->leftJoin('docentes as docente_creador', 'docente_creador.user_id', '=', 'creador.id')
            ->select([
                'examen.id',
                'examen.asignatura_id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.duracion_minutos',
                'examen.normas',
                'examen.creado_por',
                'asignatura.codigo as asignatura_codigo',
                'asignatura.nombre as asignatura_nombre',
                'creador.nombre as creador_nombre',
                'docente_creador.nombre_completo as creador_docente',
            ]);
    }

    /**
     * Examenes del docente: los que registro o los que incluyen un grupo
     * suyo.
     */
    private function delDocente(Builder $consulta, string $alias, int $usuarioId): void
    {
        $consulta
            ->where($alias.'.creado_por', $usuarioId)
            ->orWhereExists(function (Builder $propio) use ($alias, $usuarioId): void {
                $propio->selectRaw('1')
                    ->from('examen_grupo as incluido')
                    ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
                    ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
                    ->whereColumn('incluido.examen_id', $alias.'.id')
                    ->where('docente.user_id', $usuarioId);
            });
    }

    /**
     * @return list<ExamenDetalleData>
     */
    private function armar(Builder $consulta, int $usuarioId): array
    {
        $filas = [];

        foreach ($consulta->get() as $fila) {
            $columnas = $this->columnas($fila);
            $filas[$this->entero($columnas['id'] ?? null)] = $columnas;
        }

        if ($filas === []) {
            return [];
        }

        $ids = array_keys($filas);
        $grupos = $this->gruposPorExamen($ids, $usuarioId);
        $aulas = $this->aulasPorExamen($ids);
        $inscritos = $this->inscritosPorExamen($ids);
        $condiciones = $this->condicionesPorExamen($ids);
        $normas = $this->normasPorExamen($ids);
        $juntos = $this->juntosPorExamen($ids, $usuarioId);
        $ahora = now();
        $hoy = $ahora->toDateString();
        $instante = $ahora->format('Y-m-d H:i');

        $examenes = [];

        foreach ($filas as $id => $columnas) {
            $gruposDelExamen = $grupos[$id] ?? [];
            $aulasDelExamen = $aulas[$id] ?? [];
            $tipo = $this->cadena($columnas['tipo'] ?? null);
            $fecha = $this->fecha($columnas['fecha'] ?? null);
            $hora = $this->hora($columnas['hora_inicio'] ?? null);
            $duracion = $this->entero($columnas['duracion_minutos'] ?? null);
            $total = $inscritos[$id] ?? 0;
            $habilitados = $condiciones[$id]['habilitados'] ?? 0;
            $noHabilitados = $condiciones[$id]['no_habilitados'] ?? 0;
            $sinRevisar = max(0, $total - $habilitados - $noHabilitados);

            $resumen = new ExamenResumenData(
                id: $id,
                asignaturaId: $this->entero($columnas['asignatura_id'] ?? null),
                asignaturaCodigo: $this->cadena($columnas['asignatura_codigo'] ?? null),
                asignaturaNombre: $this->cadena($columnas['asignatura_nombre'] ?? null),
                tipo: $tipo,
                tipoTexto: $this->tipoTexto($tipo),
                fecha: $fecha,
                hora: $hora,
                duracion: $duracion,
                grupos: array_map(
                    static fn (GrupoDeExamenData $grupo): string => $grupo->codigo,
                    $gruposDelExamen,
                ),
                inscritos: $total,
                aulas: array_map(
                    static fn (AulaDetalleData $aula): string => $aula->nombre,
                    $aulasDelExamen,
                ),
                habilitados: $habilitados,
                noHabilitados: $noHabilitados,
                sinRevisar: $sinRevisar,
                // Los codigos QR todavia no se emiten.
                qrEmitidos: 0,
                avance: $this->avance(count($aulasDelExamen), $habilitados, $sinRevisar),
                propio: $this->entero($columnas['creado_por'] ?? null) === $usuarioId,
                registradoPor: $this->texto($columnas['creador_docente'] ?? null)
                    ?? $this->cadena($columnas['creador_nombre'] ?? null),
                juntoCon: $juntos[$id] ?? 0,
                esHoy: $fecha === $hoy,
                rendido: $this->finDe($fecha, $hora, $duracion) <= $instante,
            );

            $examenes[] = new ExamenDetalleData(
                resumen: $resumen,
                normas: $this->texto($columnas['normas'] ?? null),
                grupos: $gruposDelExamen,
                aulas: $aulasDelExamen,
                normasMarcadas: $normas[$id] ?? [],
            );
        }

        return $examenes;
    }

    private function avance(int $aulas, int $habilitados, int $sinRevisar): AvanceData
    {
        $avance = AvanceDeExamen::calcular($aulas, $habilitados, $sinRevisar);

        return new AvanceData(
            grupos: $avance['grupos'],
            aulas: $avance['aulas'],
            habilitacion: $avance['habilitacion'],
            qr: $avance['qr'],
            estado: $avance['estado'],
            accion: $avance['accion'],
            paso: $avance['paso'],
        );
    }

    /**
     * @param  list<int>  $examenIds
     * @return array<int, list<GrupoDeExamenData>>
     */
    private function gruposPorExamen(array $examenIds, int $usuarioId): array
    {
        $pares = DB::table('examen_grupo')
            ->whereIn('examen_id', $examenIds)
            ->get(['examen_id', 'grupo_id']);

        $grupoIds = [];

        foreach ($pares as $par) {
            $grupoIds[] = $this->entero($this->columnas($par)['grupo_id'] ?? null);
        }

        $datos = [];

        if ($grupoIds !== []) {
            $encontrados = $this->grupos(
                DB::table('grupos as grupo')->whereIn('grupo.id', array_values(array_unique($grupoIds))),
                $usuarioId,
            );

            foreach ($encontrados as $grupo) {
                $datos[$grupo->id] = $grupo;
            }
        }

        $porExamen = [];

        foreach ($pares as $par) {
            $columnas = $this->columnas($par);
            $grupo = $datos[$this->entero($columnas['grupo_id'] ?? null)] ?? null;

            if ($grupo !== null) {
                $porExamen[$this->entero($columnas['examen_id'] ?? null)][] = $grupo;
            }
        }

        foreach ($porExamen as $examenId => $lista) {
            usort(
                $lista,
                static fn (GrupoDeExamenData $a, GrupoDeExamenData $b): int => strnatcasecmp($a->codigo, $b->codigo),
            );

            $porExamen[$examenId] = $lista;
        }

        return $porExamen;
    }

    /**
     * @return list<GrupoDeExamenData>
     */
    private function grupos(Builder $consulta, int $usuarioId): array
    {
        $filas = $consulta
            ->join('periodos as periodo', 'periodo.id', '=', 'grupo.periodo_id')
            ->leftJoin('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
            ->select([
                'grupo.id',
                'grupo.codigo',
                'grupo.periodo_id',
                'periodo.codigo as periodo_codigo',
                'periodo.fecha_inicio',
                'periodo.fecha_fin',
                'docente.nombre_completo as docente',
                'docente.user_id as docente_cuenta',
            ])
            ->selectSub(
                DB::table('inscripciones as inscripcion')
                    ->selectRaw('count(*)')
                    ->whereColumn('inscripcion.grupo_id', 'grupo.id'),
                'inscritos',
            )
            ->get();

        $grupos = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $inicio = $this->texto($columnas['fecha_inicio'] ?? null);
            $fin = $this->texto($columnas['fecha_fin'] ?? null);

            $grupos[] = new GrupoDeExamenData(
                id: $this->entero($columnas['id'] ?? null),
                codigo: $this->cadena($columnas['codigo'] ?? null),
                docente: $this->texto($columnas['docente'] ?? null),
                inscritos: $this->entero($columnas['inscritos'] ?? null),
                propio: $this->enteroONulo($columnas['docente_cuenta'] ?? null) === $usuarioId,
                periodoId: $this->entero($columnas['periodo_id'] ?? null),
                periodoCodigo: $this->cadena($columnas['periodo_codigo'] ?? null),
                periodoInicio: $inicio === null ? null : $this->fecha($inicio),
                periodoFin: $fin === null ? null : $this->fecha($fin),
            );
        }

        return $grupos;
    }

    /**
     * @param  list<int>  $examenIds
     * @return array<int, list<AulaDetalleData>>
     */
    private function aulasPorExamen(array $examenIds): array
    {
        $filas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->leftJoin('edificios as edificio', 'edificio.id', '=', 'aula.edificio_id')
            ->whereIn('asignada.examen_id', $examenIds)
            ->orderBy('asignada.id')
            ->get([
                'asignada.examen_id',
                'asignada.aula_id',
                'aula.nombre',
                'aula.piso',
                'aula.edificio_id',
                'edificio.nombre as edificio',
            ]);

        $porExamen = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $edificio = $this->texto($columnas['edificio'] ?? null);
            $piso = $this->texto($columnas['piso'] ?? null);

            $porExamen[$this->entero($columnas['examen_id'] ?? null)][] = new AulaDetalleData(
                aulaId: $this->entero($columnas['aula_id'] ?? null),
                nombre: $this->cadena($columnas['nombre'] ?? null),
                ubicacion: $edificio === null
                    ? null
                    : $edificio.($piso === null || $piso === '' ? '' : ' · '.$piso),
                edificioId: $this->enteroONulo($columnas['edificio_id'] ?? null),
            );
        }

        foreach ($porExamen as $examenId => $lista) {
            usort(
                $lista,
                static fn (AulaDetalleData $a, AulaDetalleData $b): int => strnatcasecmp($a->nombre, $b->nombre),
            );

            $porExamen[$examenId] = $lista;
        }

        return $porExamen;
    }

    /**
     * Las normas marcadas de cada examen, con el texto con que se
     * guardaron y en el orden en que se muestran.
     *
     * @param  list<int>  $examenIds
     * @return array<int, list<NormaMarcadaData>>
     */
    private function normasPorExamen(array $examenIds): array
    {
        $filas = DB::table('examen_norma as norma')
            ->whereIn('norma.examen_id', $examenIds)
            ->orderBy('norma.orden')
            ->orderBy('norma.id')
            ->get(['norma.id', 'norma.examen_id', 'norma.plantilla_id', 'norma.texto']);

        $porExamen = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $porExamen[$this->entero($columnas['examen_id'] ?? null)][] = new NormaMarcadaData(
                id: $this->entero($columnas['id'] ?? null),
                plantillaId: $this->enteroONulo($columnas['plantilla_id'] ?? null),
                texto: $this->cadena($columnas['texto'] ?? null),
            );
        }

        return $porExamen;
    }

    /**
     * Estudiantes distintos inscritos en los grupos de cada examen: quien
     * esta en dos grupos cuenta una vez.
     *
     * @param  list<int>  $examenIds
     * @return array<int, int>
     */
    private function inscritosPorExamen(array $examenIds): array
    {
        $filas = DB::table('examen_grupo as incluido')
            ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
            ->whereIn('incluido.examen_id', $examenIds)
            ->groupBy('incluido.examen_id')
            ->selectRaw('incluido.examen_id, count(distinct inscripcion.estudiante_id) as total')
            ->get();

        $inscritos = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $inscritos[$this->entero($columnas['examen_id'] ?? null)] = $this->entero($columnas['total'] ?? null);
        }

        return $inscritos;
    }

    /**
     * Habilitados y no habilitados, contando solo a quienes siguen
     * inscritos en algun grupo del examen.
     *
     * @param  list<int>  $examenIds
     * @return array<int, array{habilitados: int, no_habilitados: int}>
     */
    private function condicionesPorExamen(array $examenIds): array
    {
        $filas = DB::table('habilitaciones as habilitacion')
            ->whereIn('habilitacion.examen_id', $examenIds)
            ->whereExists(function (Builder $inscrito): void {
                $inscrito->selectRaw('1')
                    ->from('examen_grupo as incluido')
                    ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
                    ->whereColumn('incluido.examen_id', 'habilitacion.examen_id')
                    ->whereColumn('inscripcion.estudiante_id', 'habilitacion.estudiante_id');
            })
            ->groupBy('habilitacion.examen_id')
            ->selectRaw(
                'habilitacion.examen_id,
                 sum(case when habilitacion.habilitado then 1 else 0 end) as habilitados,
                 sum(case when habilitacion.habilitado then 0 else 1 end) as no_habilitados'
            )
            ->get();

        $condiciones = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $condiciones[$this->entero($columnas['examen_id'] ?? null)] = [
                'habilitados' => $this->entero($columnas['habilitados'] ?? null),
                'no_habilitados' => $this->entero($columnas['no_habilitados'] ?? null),
            ];
        }

        return $condiciones;
    }

    /**
     * Otros examenes del mismo docente con igual fecha y hora y al menos un
     * aula en comun.
     *
     * @param  list<int>  $examenIds
     * @return array<int, int>
     */
    private function juntosPorExamen(array $examenIds, int $usuarioId): array
    {
        $filas = DB::table('examenes as examen')
            ->join('examenes as otro', function (JoinClause $otro): void {
                $otro
                    ->on('otro.fecha', '=', 'examen.fecha')
                    ->on('otro.hora_inicio', '=', 'examen.hora_inicio')
                    ->on('otro.id', '<>', 'examen.id');
            })
            ->whereIn('examen.id', $examenIds)
            ->where(function (Builder $visibles) use ($usuarioId): void {
                $this->delDocente($visibles, 'otro', $usuarioId);
            })
            ->whereExists(function (Builder $comun): void {
                $comun->selectRaw('1')
                    ->from('examen_aula as una')
                    ->join('examen_aula as otra', 'otra.aula_id', '=', 'una.aula_id')
                    ->whereColumn('una.examen_id', 'examen.id')
                    ->whereColumn('otra.examen_id', 'otro.id');
            })
            ->groupBy('examen.id')
            ->selectRaw('examen.id, count(distinct otro.id) as total')
            ->get();

        $juntos = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $juntos[$this->entero($columnas['id'] ?? null)] = $this->entero($columnas['total'] ?? null);
        }

        return $juntos;
    }

    private function tipoTexto(string $tipo): string
    {
        return TipoExamen::tryFrom($tipo)?->etiqueta() ?? $tipo;
    }

    private function minutos(string $hora): int
    {
        return ((int) substr($hora, 0, 2)) * 60 + (int) substr($hora, 3, 2);
    }

    /**
     * El instante en que termina el examen, `AAAA-MM-DD HH:MM`, comparable
     * como texto.
     */
    private function finDe(string $fecha, string $hora, int $duracion): string
    {
        $fin = strtotime("{$fecha} {$hora}:00 UTC");

        return $fin === false
            ? "{$fecha} {$hora}"
            : gmdate('Y-m-d H:i', $fin + $duracion * 60);
    }
}
