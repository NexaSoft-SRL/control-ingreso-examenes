<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\EstudianteData;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Domain\Enums\MedioIdentificacion;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aulas, examenes, inscripciones y estudiantes son de otros modulos: se
 * leen por nombre de tabla, sin importar sus modelos.
 */
final class EloquentPuertaGateway implements PuertaGateway
{
    use LeeFilas;

    /**
     * Las etiquetas de `Examenes\Domain\Enums\TipoExamen`, que desde aqui
     * no se puede importar.
     */
    public const TIPOS = [
        'PRIMER_PARCIAL' => 'Primer parcial',
        'SEGUNDO_PARCIAL' => 'Segundo parcial',
        'FINAL' => 'Examen final',
        'SEGUNDA_INSTANCIA' => 'Segunda instancia',
        'MESA' => 'Examen de mesa',
    ];

    private const INICIO = '(examen.fecha + examen.hora_inicio)';

    private const FIN = "((examen.fecha + examen.hora_inicio) + (examen.duracion_minutos * interval '1 minute'))";

    /**
     * El cierre de la puerta: cuando el docente finaliza el examen, que puede
     * pasarse de su duracion; sin finalizar, al terminar el dia del examen.
     */
    private const CIERRE = "coalesce(examen.finalizado_en, examen.fecha + interval '1 day')";

    public function horaServidor(): string
    {
        return now()->format('Y-m-d\TH:i:sP');
    }

    public function hoy(): string
    {
        return now()->toDateString();
    }

    public function aula(int $aulaId): ?AulaData
    {
        $fila = DB::table('aulas as aula')
            ->leftJoin('edificios as edificio', 'edificio.id', '=', 'aula.edificio_id')
            ->where('aula.id', $aulaId)
            ->select(['aula.id', 'aula.nombre', 'aula.piso', 'aula.edificio_id', 'edificio.nombre as edificio'])
            ->first();

        return $fila === null ? null : $this->aulaDe($this->columnas($fila));
    }

    public function examen(int $examenId): ?ExamenData
    {
        $fila = $this->examenes()->where('examen.id', $examenId)->first();

        return $fila === null ? null : $this->examenDe($this->columnas($fila));
    }

    public function examenTieneAula(int $examenId, int $aulaId): bool
    {
        return DB::table('examen_aula')
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->exists();
    }

    /**
     * @return list<ExamenData>
     */
    public function examenesDelDia(int $aulaId): array
    {
        $filas = $this->delDia($this->examenes())
            ->whereExists(function (Builder $asignada) use ($aulaId): void {
                $asignada->selectRaw('1')
                    ->from('examen_aula as asignada')
                    ->whereColumn('asignada.examen_id', 'examen.id')
                    ->where('asignada.aula_id', $aulaId);
            })
            ->selectRaw(
                '(select count(*) from ingresos where ingresos.examen_id = examen.id and ingresos.aula_id = ?) as ingresados',
                [$aulaId],
            )
            ->selectRaw(
                '(select count(*) from habilitaciones where habilitaciones.examen_id = examen.id
                    and habilitaciones.aula_id = ? and habilitaciones.habilitado) as asignados',
                [$aulaId],
            )
            // Asignados aqui que todavia no entraron por ninguna puerta: la
            // misma cifra que «Faltan» en En curso y en la lista del aula.
            ->selectRaw(
                '(select count(*) from habilitaciones as h where h.examen_id = examen.id
                    and h.aula_id = ? and h.habilitado
                    and not exists (select 1 from ingresos as i where i.examen_id = h.examen_id
                        and i.estudiante_id = h.estudiante_id)) as faltan',
                [$aulaId],
            )
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->get();

        $examenes = [];

        foreach ($filas as $fila) {
            $examenes[] = $this->examenDe($this->columnas($fila));
        }

        return $examenes;
    }

    /**
     * @return list<array{aula: AulaData, examen: ExamenData}>
     */
    public function aulasDelDia(int $usuarioId): array
    {
        $filas = $this->delDia($this->examenes())
            ->join('examen_aula as asignada', 'asignada.examen_id', '=', 'examen.id')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->leftJoin('edificios as edificio', 'edificio.id', '=', 'aula.edificio_id')
            ->where(function (Builder $suyas) use ($usuarioId): void {
                $suyas
                    ->where('examen.creado_por', $usuarioId)
                    ->orWhere('asignada.encargado_id', $usuarioId)
                    ->orWhereExists(function (Builder $grupo) use ($usuarioId): void {
                        $grupo->selectRaw('1')
                            ->from('examen_grupo as incluido')
                            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
                            ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
                            ->whereColumn('incluido.examen_id', 'examen.id')
                            ->where('docente.user_id', $usuarioId);
                    })
                    ->orWhereExists(function (Builder $apoyo) use ($usuarioId): void {
                        $apoyo->selectRaw('1')
                            ->from('examen_aula_apoyo as apoyo')
                            ->whereColumn('apoyo.examen_aula_id', 'asignada.id')
                            ->where('apoyo.user_id', $usuarioId);
                    });
            })
            ->addSelect([
                'aula.id as aula_id',
                'aula.nombre as aula_nombre',
                'aula.piso as aula_piso',
                'aula.edificio_id as aula_edificio_id',
                'edificio.nombre as aula_edificio',
            ])
            ->orderBy('aula.nombre')
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->get();

        $aulas = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $aulas[] = [
                'aula' => new AulaData(
                    id: $this->entero($columnas['aula_id'] ?? null),
                    nombre: $this->texto($columnas['aula_nombre'] ?? null) ?? '',
                    edificio: $this->texto($columnas['aula_edificio'] ?? null),
                    piso: $this->texto($columnas['aula_piso'] ?? null),
                    edificioId: $this->enteroONulo($columnas['aula_edificio_id'] ?? null),
                ),
                'examen' => $this->examenDe($columnas),
            ];
        }

        return $aulas;
    }

    /**
     * @param  list<int>  $examenIds
     * @return array{ingresos: list<array<string, mixed>>, total: int}
     */
    public function ingresosDelAula(int $aulaId, array $examenIds, ?int $limite, ?string $buscar): array
    {
        $base = DB::table('ingresos as ingreso')
            ->where('ingreso.aula_id', $aulaId)
            ->whereIn('ingreso.examen_id', $examenIds);

        $total = (clone $base)->count();

        $consulta = $base
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'ingreso.estudiante_id')
            ->select([
                'ingreso.id',
                'ingreso.estudiante_id',
                'ingreso.examen_id',
                'ingreso.es_excepcion',
                'ingreso.registrado_en',
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
            ])
            ->selectRaw(
                'exists(select 1 from incidencias where incidencias.ingreso_id = ingreso.id and incidencias.tipo = ?) as expulsado',
                [TipoIncidencia::Expulsion->value],
            )
            ->orderByDesc('ingreso.registrado_en')
            ->orderByDesc('ingreso.id');

        $patron = $this->patron($buscar);

        if ($patron !== null) {
            $consulta->where(function (Builder $texto) use ($patron): void {
                $texto
                    ->whereRaw(
                        $this->sinTildes("estudiante.apellidos || ' ' || estudiante.nombres").' like ?',
                        [$patron],
                    )
                    ->orWhere('estudiante.codigo_universitario', 'like', $patron);
            });
        }

        if ($limite !== null) {
            $consulta->limit($limite);
        }

        $ingresos = [];

        foreach ($consulta->get() as $fila) {
            $columnas = $this->columnas($fila);

            $ingresos[] = [
                'id' => $this->entero($columnas['id'] ?? null),
                'estudiante_id' => $this->entero($columnas['estudiante_id'] ?? null),
                'codigo' => $this->texto($columnas['codigo_universitario'] ?? null),
                'nombre' => $this->nombreDe($columnas['apellidos'] ?? null, $columnas['nombres'] ?? null),
                'hora' => $this->hora($this->texto($columnas['registrado_en'] ?? null), false),
                'examen_id' => $this->entero($columnas['examen_id'] ?? null),
                'excepcion' => $this->booleano($columnas['es_excepcion'] ?? null),
                'expulsado' => $this->booleano($columnas['expulsado'] ?? null),
            ];
        }

        return ['ingresos' => $ingresos, 'total' => $total];
    }

    /**
     * @param  list<int>  $examenIds
     * @return list<string>
     */
    public function facultadesDelMapa(int $aulaId, array $examenIds): array
    {
        $claves = DB::table('aulas as aula')
            ->join('edificios as edificio', 'edificio.id', '=', 'aula.edificio_id')
            ->join('facultades as facultad', 'facultad.id', '=', 'edificio.facultad_id')
            ->where(function (Builder $aulas) use ($aulaId, $examenIds): void {
                $aulas->where('aula.id', $aulaId)
                    ->orWhereIn('aula.id', function (Builder $delExamen) use ($examenIds): void {
                        $delExamen->select('asignada.aula_id')
                            ->from('examen_aula as asignada')
                            ->whereIn('asignada.examen_id', $examenIds);
                    });
            })
            ->distinct()
            ->orderBy('facultad.clave')
            ->pluck('facultad.clave')
            ->all();

        $facultades = [];

        foreach ($claves as $clave) {
            $facultades[] = $this->texto($clave) ?? '';
        }

        return $facultades;
    }

    public function estudiante(int $estudianteId): ?EstudianteData
    {
        $fila = DB::table('estudiantes')->where('id', $estudianteId)->first();

        return $fila === null ? null : $this->estudianteDe($this->columnas($fila));
    }

    /**
     * @return array{estudiante: EstudianteData, medio: MedioIdentificacion}|null
     */
    public function estudiantePorIdentificador(string $identificador): ?array
    {
        $porCodigo = DB::table('estudiantes')
            ->where('codigo_universitario', $identificador)
            ->first();

        if ($porCodigo !== null) {
            return [
                'estudiante' => $this->estudianteDe($this->columnas($porCodigo)),
                'medio' => MedioIdentificacion::Codigo,
            ];
        }

        // El C.I. se compara sin espacios, guiones ni mayusculas: «5003748-1A»,
        // «5003748 1A» y «50037481a» son el mismo documento.
        $normalizado = mb_strtoupper(str_replace([' ', '-'], '', $identificador));

        $porDocumento = DB::table('estudiantes')
            ->whereRaw("upper(replace(replace(documento_identidad, ' ', ''), '-', '')) = ?", [$normalizado])
            ->orderBy('id')
            ->first();

        if ($porDocumento === null) {
            return null;
        }

        return [
            'estudiante' => $this->estudianteDe($this->columnas($porDocumento)),
            'medio' => MedioIdentificacion::Documento,
        ];
    }

    /**
     * @param  list<int>  $examenIds
     * @return list<int>
     */
    public function examenesDondeEstaInscrito(int $estudianteId, array $examenIds): array
    {
        if ($examenIds === []) {
            return [];
        }

        $ids = DB::table('examen_grupo as incluido')
            ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
            ->where('inscripcion.estudiante_id', $estudianteId)
            ->whereNull('inscripcion.baja_en')
            ->whereIn('incluido.examen_id', $examenIds)
            ->distinct()
            ->orderBy('incluido.examen_id')
            ->pluck('incluido.examen_id')
            ->all();

        return array_values(array_map(fn (mixed $id): int => $this->entero($id), $ids));
    }

    /**
     * @param  list<int>  $excluidos
     * @return array{id: int, texto: string, aula: string|null}|null
     */
    public function examenCorrespondiente(int $estudianteId, array $excluidos): ?array
    {
        $fila = $this->examenes()
            ->whereRaw($this->abierto(), $this->enlacesDeAbierto())
            ->whereNotIn('examen.id', $excluidos)
            ->whereExists(function (Builder $inscrito) use ($estudianteId): void {
                $inscrito->selectRaw('1')
                    ->from('examen_grupo as incluido')
                    ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
                    ->whereColumn('incluido.examen_id', 'examen.id')
                    ->where('inscripcion.estudiante_id', $estudianteId)
                    ->whereNull('inscripcion.baja_en');
            })
            ->selectRaw(
                '(select aulas.nombre from habilitaciones join aulas on aulas.id = habilitaciones.aula_id
                    where habilitaciones.examen_id = examen.id and habilitaciones.estudiante_id = ?) as aula_asignada',
                [$estudianteId],
            )
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);
        $examen = $this->examenDe($columnas);

        return [
            'id' => $examen->id,
            'texto' => $examen->texto(),
            'aula' => $this->texto($columnas['aula_asignada'] ?? null),
        ];
    }

    /**
     * Los examenes con su asignatura y con lo que depende de la hora del
     * servidor: si estan abiertos y si son de hoy.
     */
    private function examenes(): Builder
    {
        return DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->select([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.normas',
                'asignatura.nombre as asignatura',
            ])
            ->selectRaw('('.$this->abierto().') as abierto', $this->enlacesDeAbierto())
            ->selectRaw('(examen.fecha = ?::date) as es_hoy', [$this->hoy()])
            ->selectRaw(
                '(select json_agg(marcada.texto order by marcada.orden, marcada.id) from examen_norma as marcada
                    where marcada.examen_id = examen.id) as normas_marcadas',
            )
            ->selectRaw('to_char('.self::FIN.", 'YYYY-MM-DD HH24:MI:SS') as fin");
    }

    /**
     * Los de hoy y los que siguen abiertos (un examen que cruza la
     * medianoche).
     */
    private function delDia(Builder $examenes): Builder
    {
        return $examenes->where(function (Builder $dia): void {
            $dia->whereRaw('examen.fecha = ?::date', [$this->hoy()])
                ->orWhereRaw($this->abierto(), $this->enlacesDeAbierto());
        });
    }

    /**
     * La ventana de un examen: desde la apertura hasta su cierre.
     */
    private function abierto(): string
    {
        return self::INICIO." - (? * interval '1 minute') <= ?::timestamp and ?::timestamp < ".self::CIERRE;
    }

    /**
     * @return list<int|string>
     */
    private function enlacesDeAbierto(): array
    {
        return [$this->minutosDeApertura(), $this->ahora(), $this->ahora()];
    }

    /**
     * @param  array<string, mixed>  $columnas
     */
    private function examenDe(array $columnas): ExamenData
    {
        $tipo = $this->texto($columnas['tipo'] ?? null) ?? '';

        return new ExamenData(
            id: $this->entero($columnas['id'] ?? null),
            asignatura: $this->texto($columnas['asignatura'] ?? null) ?? '',
            tipo: self::TIPOS[$tipo] ?? $tipo,
            fecha: substr($this->texto($columnas['fecha'] ?? null) ?? '', 0, 10),
            hora: substr($this->texto($columnas['hora_inicio'] ?? null) ?? '', 0, 5),
            abierto: $this->booleano($columnas['abierto'] ?? null),
            esHoy: $this->booleano($columnas['es_hoy'] ?? null),
            hasta: $this->iso($this->texto($columnas['fin'] ?? null)) ?? '',
            normas: $this->texto($columnas['normas'] ?? null),
            ingresados: $this->entero($columnas['ingresados'] ?? 0),
            asignados: $this->entero($columnas['asignados'] ?? 0),
            faltan: $this->entero($columnas['faltan'] ?? 0),
            normasMarcadas: $this->textos($columnas['normas_marcadas'] ?? null),
        );
    }

    /**
     * Una lista de textos que la base entrega como JSON.
     *
     * @return list<string>
     */
    private function textos(mixed $json): array
    {
        $valores = is_string($json) ? json_decode($json, true) : null;
        $textos = [];

        foreach (is_array($valores) ? $valores : [] as $valor) {
            if (is_string($valor) && $valor !== '') {
                $textos[] = $valor;
            }
        }

        return $textos;
    }

    /**
     * @param  array<string, mixed>  $columnas
     */
    private function aulaDe(array $columnas): AulaData
    {
        return new AulaData(
            id: $this->entero($columnas['id'] ?? null),
            nombre: $this->texto($columnas['nombre'] ?? null) ?? '',
            edificio: $this->texto($columnas['edificio'] ?? null),
            piso: $this->texto($columnas['piso'] ?? null),
            edificioId: $this->enteroONulo($columnas['edificio_id'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $columnas
     */
    private function estudianteDe(array $columnas): EstudianteData
    {
        return new EstudianteData(
            id: $this->entero($columnas['id'] ?? null),
            codigo: $this->texto($columnas['codigo_universitario'] ?? null) ?? '',
            nombre: $this->nombreDe($columnas['apellidos'] ?? null, $columnas['nombres'] ?? null),
        );
    }
}
