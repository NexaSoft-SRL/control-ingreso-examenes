<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;
use App\Modules\Academico\Application\DTOs\FiltroAsignaturasData;
use App\Modules\Academico\Application\DTOs\PaginaData;
use App\Modules\Academico\Domain\Enums\EstadoImportacion;
use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Periodo;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class EloquentConsultaOfertaGateway implements ConsultaOfertaGateway
{
    use LeeFilas;

    public function facultades(): array
    {
        $edificios = $this->conteoPorFacultad('edificios');
        $aulas = $this->conteoPorFacultad('aulas');
        $facultades = [];

        foreach (DB::table('facultades')->orderBy('orden')->orderBy('id')->get() as $fila) {
            $id = $this->entero($fila->id);

            $facultades[] = [
                'id' => $id,
                'clave' => $this->cadena($fila->clave),
                'sigla' => $this->cadena($fila->sigla),
                'nombre' => $this->cadena($fila->nombre),
                'color' => $this->cadena($fila->color),
                'edificios' => $edificios[$id] ?? 0,
                'aulas' => $aulas[$id] ?? 0,
            ];
        }

        return $facultades;
    }

    public function facultadPorClave(string $clave): ?array
    {
        $fila = $this->primera(DB::table('facultades')->where('clave', mb_strtolower($clave)));

        if ($fila === null) {
            return null;
        }

        return [
            'id' => $this->entero($fila->id),
            'clave' => $this->cadena($fila->clave),
            'sigla' => $this->cadena($fila->sigla),
            'nombre' => $this->cadena($fila->nombre),
        ];
    }

    public function periodos(PaginaData $pagina): array
    {
        $hoy = $this->hoy();

        $periodos = array_values(
            Periodo::query()
                ->orderByRaw(
                    'case when fecha_inicio <= ? and fecha_fin >= ? then 0 else 1 end',
                    [$hoy, $hoy],
                )
                ->orderByDesc('anio')
                ->orderByDesc('numero')
                ->forPage($pagina->pagina, $pagina->porPagina)
                ->get()
                ->all()
        );

        return [
            'filas' => $this->filasDePeriodos($periodos),
            'total' => Periodo::query()->count(),
        ];
    }

    public function periodo(int $periodoId): ?array
    {
        $periodo = Periodo::query()->find($periodoId);

        if (! $periodo instanceof Periodo) {
            return null;
        }

        return $this->filasDePeriodos([$periodo])[0] ?? null;
    }

    public function pendientes(array $periodoIds): array
    {
        $grupos = DB::table('grupos as g');
        $this->enPeriodos($grupos, 'g.periodo_id', $periodoIds);

        $sinLista = (clone $grupos)->whereNotExists(function (Builder $inscritos): void {
            $inscritos->selectRaw('1')
                ->from('inscripciones as i')
                ->whereColumn('i.grupo_id', 'g.id')
                ->whereNull('i.baja_en');
        });

        return [
            'docentes' => DB::table('docentes')->count(),
            'docentes_sin_cuenta' => DB::table('docentes')->whereNull('user_id')->count(),
            'grupos' => $grupos->count(),
            'grupos_sin_lista' => $sinLista->count(),
        ];
    }

    public function ofertaPorFacultad(array $periodoIds): array
    {
        $carreras = $this->conteoPorFacultad('carreras');
        $aulas = $this->conteoPorFacultad('aulas');

        $consultaGrupos = DB::table('grupos as g')
            ->groupBy('g.facultad_id')
            ->selectRaw('g.facultad_id, count(*) as total');
        $this->enPeriodos($consultaGrupos, 'g.periodo_id', $periodoIds);

        $grupos = [];

        foreach ($consultaGrupos->get() as $fila) {
            $grupos[$this->entero($fila->facultad_id)] = $this->entero($fila->total);
        }

        $oferta = [];

        foreach (DB::table('facultades')->orderBy('orden')->orderBy('id')->get() as $fila) {
            $id = $this->entero($fila->id);
            $importacion = $this->importacionDe($id);

            $oferta[] = [
                'sigla' => $this->cadena($fila->sigla),
                'nombre' => $this->cadena($fila->nombre),
                'carreras' => $carreras[$id] ?? 0,
                'grupos' => $grupos[$id] ?? 0,
                'aulas' => $aulas[$id] ?? 0,
                'importacion' => [
                    'estado' => $importacion['estado'] ?? 'sin',
                    'fecha' => $importacion['fecha'] ?? null,
                    'error' => $importacion['error'] ?? null,
                ],
            ];
        }

        return $oferta;
    }

    public function importacionEnCurso(string $claveFacultad, int $minutos): bool
    {
        return DB::table('importaciones_oferta')
            ->where('facultad_id', $this->facultadId($claveFacultad))
            ->where('estado', EstadoImportacion::Importando->value)
            ->where('iniciada_en', '>', now()->subMinutes($minutos))
            ->exists();
    }

    public function carreras(?string $claveFacultad): array
    {
        $consulta = DB::table('carreras')->orderByRaw($this->sinTildes('nombre'))->orderBy('id');

        if ($this->hayTexto($claveFacultad)) {
            $consulta->where('facultad_id', $this->facultadId((string) $claveFacultad));
        }

        $carreras = [];

        foreach ($consulta->get(['id', 'codigo', 'nombre', 'regimen']) as $fila) {
            $regimen = RegimenCarrera::tryFrom($this->cadena($fila->regimen));

            $carreras[] = [
                'id' => $this->entero($fila->id),
                'codigo' => $this->cadena($fila->codigo),
                'nombre' => $this->cadena($fila->nombre),
                'regimen' => $regimen instanceof RegimenCarrera
                    ? $regimen->etiqueta()
                    : $this->cadena($fila->regimen),
            ];
        }

        return $carreras;
    }

    public function asignaturas(FiltroAsignaturasData $filtro, array $periodoIds, PaginaData $pagina): array
    {
        $facultadId = $this->hayTexto($filtro->facultad)
            ? $this->facultadId((string) $filtro->facultad)
            : null;
        $carreraId = $filtro->carreraId;

        // La oferta de ese periodo y esa facultad (y esa carrera): de aqui
        // salen los niveles que ofrece el filtro.
        $oferta = DB::table('asignaturas as a')
            ->whereExists(function (Builder $grupos) use ($periodoIds, $facultadId): void {
                $this->gruposDeLaAsignatura($grupos, $periodoIds, $facultadId);
            });

        if ($carreraId !== null) {
            $oferta->whereExists(function (Builder $plan) use ($carreraId): void {
                $plan->selectRaw('1')
                    ->from('plan_estudios as pe')
                    ->whereColumn('pe.asignatura_id', 'a.id')
                    ->where('pe.carrera_id', $carreraId);
            });
        }

        $niveles = $this->nivelesDe((clone $oferta)->select('a.id'), $facultadId, $carreraId);

        $consulta = clone $oferta;

        if ($this->hayTexto($filtro->nivel)) {
            $nivel = mb_strtoupper(trim((string) $filtro->nivel));

            $consulta->whereExists(function (Builder $plan) use ($nivel, $facultadId, $carreraId): void {
                $plan->selectRaw('1')
                    ->from('plan_estudios as pe')
                    ->join('carreras as c', 'c.id', '=', 'pe.carrera_id')
                    ->whereColumn('pe.asignatura_id', 'a.id')
                    ->whereRaw('upper(pe.nivel) = ?', [$nivel]);
                $this->delPlan($plan, $facultadId, $carreraId);
            });
        }

        if ($filtro->sinLista) {
            $consulta->whereExists(function (Builder $grupos) use ($periodoIds, $facultadId): void {
                $this->gruposDeLaAsignatura($grupos, $periodoIds, $facultadId);
                $grupos->whereNotExists(function (Builder $inscritos): void {
                    $inscritos->selectRaw('1')
                        ->from('inscripciones as i')
                        ->whereColumn('i.grupo_id', 'g.id')
                        ->whereNull('i.baja_en');
                });
            });
        }

        if ($this->hayTexto($filtro->buscar)) {
            $patron = $this->patron((string) $filtro->buscar);

            $consulta->where(function (Builder $texto) use ($patron, $periodoIds, $facultadId): void {
                $texto->whereRaw($this->sinTildes('a.nombre').' like ?', [$patron])
                    ->orWhereRaw('lower(a.codigo) like ?', [$patron])
                    ->orWhereExists(function (Builder $grupos) use ($patron, $periodoIds, $facultadId): void {
                        $this->gruposDeLaAsignatura($grupos, $periodoIds, $facultadId);
                        $grupos->join('docentes as d', 'd.id', '=', 'g.docente_id')
                            ->whereRaw($this->sinTildes('d.nombre_completo').' like ?', [$patron]);
                    });
            });
        }

        $total = (clone $consulta)->count();

        $totalGrupos = DB::table('grupos as g')
            ->whereIn('g.asignatura_id', (clone $consulta)->select('a.id'));
        $this->enPeriodos($totalGrupos, 'g.periodo_id', $periodoIds);

        if ($facultadId !== null) {
            $totalGrupos->where('g.facultad_id', $facultadId);
        }

        $filas = (clone $consulta)
            ->orderByRaw($this->sinTildes('a.nombre'))
            ->orderBy('a.id')
            ->forPage($pagina->pagina, $pagina->porPagina)
            ->get(['a.id', 'a.codigo', 'a.nombre']);

        $ids = [];

        foreach ($filas as $fila) {
            $ids[] = $this->entero($fila->id);
        }

        [$grupos, $inscritos] = $this->cifrasDeAsignaturas($ids, $periodoIds, $facultadId);
        $nivelDe = $this->nivelPorAsignatura($ids, $facultadId, $carreraId);
        $asignaturas = [];

        foreach ($filas as $fila) {
            $id = $this->entero($fila->id);

            $asignaturas[] = [
                'id' => $id,
                'codigo' => $this->cadena($fila->codigo),
                'nombre' => $this->cadena($fila->nombre),
                'nivel' => $nivelDe[$id] ?? null,
                'grupos' => $grupos[$id] ?? 0,
                'inscritos' => $inscritos[$id] ?? 0,
            ];
        }

        return [
            'filas' => $asignaturas,
            'total' => $total,
            'grupos' => $totalGrupos->count(),
            'niveles' => $niveles,
        ];
    }

    public function existeAsignatura(int $asignaturaId): bool
    {
        return DB::table('asignaturas')->where('id', $asignaturaId)->exists();
    }

    public function gruposDeAsignatura(int $asignaturaId, array $periodoIds, ?string $claveFacultad, ?string $buscar): array
    {
        $consulta = DB::table('grupos as g')
            ->leftJoin('docentes as d', 'd.id', '=', 'g.docente_id')
            ->where('g.asignatura_id', $asignaturaId);
        $this->enPeriodos($consulta, 'g.periodo_id', $periodoIds);

        if ($this->hayTexto($claveFacultad)) {
            $consulta->where('g.facultad_id', $this->facultadId((string) $claveFacultad));
        }

        $columnas = ['g.id', 'g.codigo', 'd.nombre_completo as docente'];

        if ($this->hayTexto($buscar)) {
            $consulta->selectRaw(
                'coalesce('.$this->sinTildes('d.nombre_completo').' like ?, false) as coincide',
                [$this->patron((string) $buscar)],
            );
        } else {
            $consulta->selectRaw('false as coincide');
        }

        $filas = [];

        foreach ($consulta->addSelect($columnas)->get() as $fila) {
            $filas[] = [
                'id' => $this->entero($fila->id),
                'codigo' => $this->cadena($fila->codigo),
                'docente' => $this->texto($fila->docente),
                'coincide' => $fila->coincide === true || $fila->coincide === 't' || $fila->coincide === 1,
            ];
        }

        usort($filas, static function (array $a, array $b): int {
            if ($a['coincide'] !== $b['coincide']) {
                return $a['coincide'] ? -1 : 1;
            }

            return strnatcasecmp($a['codigo'], $b['codigo']) ?: $a['id'] <=> $b['id'];
        });

        $ids = array_map(static fn (array $fila): int => $fila['id'], $filas);
        $horarios = $this->horariosDe($ids);
        $inscritos = $this->inscritosDe($ids);
        $grupos = [];

        foreach ($filas as $fila) {
            $grupos[] = [
                'id' => $fila['id'],
                'codigo' => $fila['codigo'],
                'docente' => $fila['docente'],
                'horarios' => $horarios[$fila['id']] ?? [],
                'inscritos' => $inscritos[$fila['id']] ?? 0,
            ];
        }

        return $grupos;
    }

    public function gruposDeDocente(int $docenteId, array $periodoIds): array
    {
        $consulta = DB::table('grupos as g')
            ->join('asignaturas as a', 'a.id', '=', 'g.asignatura_id')
            ->join('facultades as f', 'f.id', '=', 'g.facultad_id')
            ->join('periodos as p', 'p.id', '=', 'g.periodo_id')
            ->where('g.docente_id', $docenteId);
        $this->enPeriodos($consulta, 'g.periodo_id', $periodoIds);

        $filas = [];

        foreach ($consulta->get([
            'g.id', 'g.codigo', 'g.facultad_id', 'a.id as asignatura_id', 'a.codigo as asignatura_codigo',
            'a.nombre as asignatura_nombre', 'f.sigla', 'p.codigo as periodo', 'p.anio', 'p.numero',
        ]) as $fila) {
            $filas[] = [
                'id' => $this->entero($fila->id),
                'codigo' => $this->cadena($fila->codigo),
                'facultad_id' => $this->entero($fila->facultad_id),
                'asignatura_id' => $this->entero($fila->asignatura_id),
                'asignatura_codigo' => $this->cadena($fila->asignatura_codigo),
                'asignatura_nombre' => $this->cadena($fila->asignatura_nombre),
                'sigla' => $this->cadena($fila->sigla),
                'periodo' => $this->cadena($fila->periodo),
                'orden_periodo' => $this->entero($fila->anio) * 10 + $this->entero($fila->numero),
            ];
        }

        usort($filas, fn (array $a, array $b): int => ($b['orden_periodo'] <=> $a['orden_periodo'])
            ?: strcmp($this->normalizar($a['asignatura_nombre']), $this->normalizar($b['asignatura_nombre']))
            ?: strnatcasecmp($a['codigo'], $b['codigo'])
            ?: $a['id'] <=> $b['id']);

        $ids = array_map(static fn (array $fila): int => $fila['id'], $filas);
        $horarios = $this->horariosDe($ids);
        $inscritos = $this->inscritosDe($ids);
        $niveles = [];
        $grupos = [];

        foreach ($filas as $fila) {
            $clave = $fila['facultad_id'].':'.$fila['asignatura_id'];

            if (! array_key_exists($clave, $niveles)) {
                $niveles[$clave] = $this->nivelPorAsignatura(
                    [$fila['asignatura_id']],
                    $fila['facultad_id'],
                    null,
                )[$fila['asignatura_id']] ?? null;
            }

            $total = $inscritos[$fila['id']] ?? 0;

            $grupos[] = [
                'id' => $fila['id'],
                'codigo' => $fila['codigo'],
                'asignatura' => [
                    'id' => $fila['asignatura_id'],
                    'codigo' => $fila['asignatura_codigo'],
                    'nombre' => $fila['asignatura_nombre'],
                ],
                'nivel' => $niveles[$clave],
                'facultad' => $fila['sigla'],
                'periodo' => $fila['periodo'],
                'horarios' => $horarios[$fila['id']] ?? [],
                'inscritos' => $total,
                'con_lista' => $total > 0,
            ];
        }

        return $grupos;
    }

    public function codigoDePeriodo(int $periodoId): ?string
    {
        return $this->texto(DB::table('periodos')->where('id', $periodoId)->value('codigo'));
    }

    public function hoy(): string
    {
        return now()->toDateString();
    }

    /**
     * @return array<int, int>
     */
    private function conteoPorFacultad(string $tabla): array
    {
        $conteo = [];

        $filas = DB::table($tabla)
            ->whereNotNull('facultad_id')
            ->groupBy('facultad_id')
            ->selectRaw('facultad_id, count(*) as total')
            ->get();

        foreach ($filas as $fila) {
            $conteo[$this->entero($fila->facultad_id)] = $this->entero($fila->total);
        }

        return $conteo;
    }

    /**
     * @param  list<Periodo>  $periodos
     * @return list<array<string, mixed>>
     */
    private function filasDePeriodos(array $periodos): array
    {
        $ids = array_map(static fn (Periodo $periodo): int => $periodo->id, $periodos);
        $grupos = [];
        $carreras = [];

        if ($ids !== []) {
            $porPeriodo = DB::table('grupos')
                ->whereIn('periodo_id', $ids)
                ->groupBy('periodo_id')
                ->selectRaw('periodo_id, count(*) as total')
                ->get();

            foreach ($porPeriodo as $fila) {
                $grupos[$this->entero($fila->periodo_id)] = $this->entero($fila->total);
            }

            // Las carreras anuales tienen sus grupos en el periodo anual;
            // las demas, en los otros.
            $conCarrera = DB::table('grupos as g')
                ->join('periodos as p', 'p.id', '=', 'g.periodo_id')
                ->join('plan_estudios as pe', 'pe.asignatura_id', '=', 'g.asignatura_id')
                ->join('carreras as c', function (JoinClause $union): void {
                    $union->on('c.id', '=', 'pe.carrera_id')
                        ->on('c.facultad_id', '=', 'g.facultad_id');
                })
                ->whereIn('g.periodo_id', $ids)
                ->whereRaw('(p.tipo = ?) = (c.regimen = ?)', [
                    TipoPeriodo::Anual->value,
                    RegimenCarrera::Anual->value,
                ])
                ->groupBy('g.periodo_id')
                ->selectRaw('g.periodo_id, count(distinct c.id) as total')
                ->get();

            foreach ($conCarrera as $fila) {
                $carreras[$this->entero($fila->periodo_id)] = $this->entero($fila->total);
            }
        }

        $filas = [];

        foreach ($periodos as $periodo) {
            $filas[] = [
                'id' => $periodo->id,
                'codigo' => $periodo->codigo,
                'tipo' => $periodo->tipo->etiqueta(),
                'fecha_inicio' => $periodo->fecha_inicio?->toDateString(),
                'fecha_fin' => $periodo->fecha_fin?->toDateString(),
                'estado' => $periodo->estado()->etiqueta(),
                'nota' => $this->nota($carreras[$periodo->id] ?? 0, $grupos[$periodo->id] ?? 0),
            ];
        }

        return $filas;
    }

    private function nota(int $carreras, int $grupos): string
    {
        if ($grupos === 0) {
            return 'Sin oferta importada';
        }

        $partes = [];

        if ($carreras > 0) {
            $partes[] = $this->miles($carreras).($carreras === 1 ? ' carrera' : ' carreras');
        }

        $partes[] = $this->miles($grupos).($grupos === 1 ? ' grupo' : ' grupos');

        return implode(' · ', $partes);
    }

    private function miles(int $numero): string
    {
        return number_format($numero, 0, ',', '.');
    }

    /**
     * @return array{estado: string, fecha: string|null, error: string|null}|null
     */
    private function importacionDe(int $facultadId): ?array
    {
        $fila = $this->primera(
            DB::table('importaciones_oferta')
                ->where('facultad_id', $facultadId)
                ->orderByDesc('id')
        );

        if ($fila === null) {
            return null;
        }

        $fecha = $this->texto($fila->fecha_fuente)
            ?? $this->texto($fila->terminada_en)
            ?? $this->texto($fila->iniciada_en);

        return [
            'estado' => mb_strtolower($this->cadena($fila->estado)),
            'fecha' => $fecha === null ? null : substr($fecha, 0, 10),
            'error' => $this->texto($fila->error),
        ];
    }

    /**
     * Subconsulta «la asignatura `a` tiene grupos en ese periodo y esa
     * facultad».
     *
     * @param  list<int>  $periodoIds
     */
    private function gruposDeLaAsignatura(Builder $grupos, array $periodoIds, ?int $facultadId): void
    {
        $grupos->selectRaw('1')
            ->from('grupos as g')
            ->whereColumn('g.asignatura_id', 'a.id');
        $this->enPeriodos($grupos, 'g.periodo_id', $periodoIds);

        if ($facultadId !== null) {
            $grupos->where('g.facultad_id', $facultadId);
        }
    }

    /**
     * Acota una consulta sobre `plan_estudios pe` + `carreras c`.
     */
    private function delPlan(Builder $plan, ?int $facultadId, ?int $carreraId): void
    {
        if ($carreraId !== null) {
            $plan->where('pe.carrera_id', $carreraId);
        }

        if ($facultadId !== null) {
            $plan->where('c.facultad_id', $facultadId);
        }
    }

    /**
     * @return list<string>
     */
    private function nivelesDe(Builder $asignaturaIds, ?int $facultadId, ?int $carreraId): array
    {
        $plan = DB::table('plan_estudios as pe')
            ->join('carreras as c', 'c.id', '=', 'pe.carrera_id')
            ->whereIn('pe.asignatura_id', $asignaturaIds)
            ->distinct();
        $this->delPlan($plan, $facultadId, $carreraId);

        $niveles = [];

        foreach ($plan->pluck('pe.nivel') as $nivel) {
            $niveles[] = $this->nivelLegible($this->cadena($nivel));
        }

        $niveles = array_values(array_unique($niveles));
        usort($niveles, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return $niveles;
    }

    /**
     * El nivel de cada asignatura en la carrera pedida o, sin carrera, el
     * primero entre las carreras de la facultad.
     *
     * @param  list<int>  $asignaturaIds
     * @return array<int, string>
     */
    private function nivelPorAsignatura(array $asignaturaIds, ?int $facultadId, ?int $carreraId): array
    {
        if ($asignaturaIds === []) {
            return [];
        }

        $plan = DB::table('plan_estudios as pe')
            ->join('carreras as c', 'c.id', '=', 'pe.carrera_id')
            ->whereIn('pe.asignatura_id', $asignaturaIds);
        $this->delPlan($plan, $facultadId, $carreraId);

        $niveles = [];

        foreach ($plan->get(['pe.asignatura_id', 'pe.nivel']) as $fila) {
            $id = $this->entero($fila->asignatura_id);
            $nivel = $this->nivelLegible($this->cadena($fila->nivel));

            if (! isset($niveles[$id]) || strnatcasecmp($nivel, $niveles[$id]) < 0) {
                $niveles[$id] = $nivel;
            }
        }

        return $niveles;
    }

    /**
     * Grupos e inscritos de cada asignatura en ese periodo y facultad.
     *
     * @param  list<int>  $asignaturaIds
     * @param  list<int>  $periodoIds
     * @return array{array<int, int>, array<int, int>}
     */
    private function cifrasDeAsignaturas(array $asignaturaIds, array $periodoIds, ?int $facultadId): array
    {
        if ($asignaturaIds === []) {
            return [[], []];
        }

        $consulta = DB::table('grupos as g')
            ->leftJoin('inscripciones as i', function (JoinClause $inscrita): void {
                $inscrita->on('i.grupo_id', '=', 'g.id')->whereNull('i.baja_en');
            })
            ->whereIn('g.asignatura_id', $asignaturaIds)
            ->groupBy('g.asignatura_id')
            ->selectRaw('g.asignatura_id, count(distinct g.id) as grupos, count(i.id) as inscritos');
        $this->enPeriodos($consulta, 'g.periodo_id', $periodoIds);

        if ($facultadId !== null) {
            $consulta->where('g.facultad_id', $facultadId);
        }

        $grupos = [];
        $inscritos = [];

        foreach ($consulta->get() as $fila) {
            $id = $this->entero($fila->asignatura_id);
            $grupos[$id] = $this->entero($fila->grupos);
            $inscritos[$id] = $this->entero($fila->inscritos);
        }

        return [$grupos, $inscritos];
    }
}
