<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\CifrasHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\FiltroHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\GrupoDeExamenData;
use App\Modules\Habilitacion\Application\DTOs\HabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\InscritoData;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Los examenes, sus grupos, las inscripciones, los estudiantes y las aulas
 * son de otros modulos: se leen por nombre de tabla, sin importar sus
 * modelos. El SQL es de PostgreSQL (`DISTINCT ON`, `FILTER`).
 */
final class EloquentHabilitacionGateway implements HabilitacionGateway
{
    use ConvierteColumnas;

    private const LOTE = 500;

    /** Para buscar sin distinguir mayusculas ni tildes. */
    private const CON_TILDE = 'áéíóúüñÁÉÍÓÚÜÑ';

    private const SIN_TILDE = 'aeiouunaeiouun';

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function condicionDe(int $examenId, int $estudianteId): ?HabilitacionData
    {
        $fila = DB::table('habilitaciones as habilitacion')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'habilitacion.aula_id')
            ->leftJoin('usuarios as autor', 'autor.id', '=', 'habilitacion.registrada_por')
            ->where('habilitacion.examen_id', $examenId)
            ->where('habilitacion.estudiante_id', $estudianteId)
            ->select([
                'habilitacion.habilitado',
                'habilitacion.aula_id',
                'aula.nombre as aula_nombre',
                'habilitacion.motivo',
                'habilitacion.registrada_por',
                'autor.nombre as autor_nombre',
                'habilitacion.updated_at',
                'habilitacion.created_at',
            ])
            ->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return new HabilitacionData(
            examenId: $examenId,
            estudianteId: $estudianteId,
            habilitado: $this->booleano($columnas['habilitado'] ?? null),
            aulaId: $this->enteroONulo($columnas['aula_id'] ?? null),
            aulaNombre: $this->texto($columnas['aula_nombre'] ?? null),
            motivo: $this->texto($columnas['motivo'] ?? null),
            registradaPorId: $this->enteroONulo($columnas['registrada_por'] ?? null),
            registradaPor: $this->texto($columnas['autor_nombre'] ?? null),
            registradaEn: $this->instante(
                $this->texto($columnas['updated_at'] ?? null)
                    ?? $this->texto($columnas['created_at'] ?? null),
            ),
        );
    }

    public function existeExamen(int $examenId): bool
    {
        return DB::table('examenes')->where('id', $examenId)->exists();
    }

    /**
     * @return list<InscritoData>
     */
    public function listar(
        int $examenId,
        FiltroHabilitacionData $filtros,
        int $pagina,
        int $porPagina,
    ): array {
        $filas = $this->filtrada($examenId, $filtros)
            ->leftJoin('aulas as aula', 'aula.id', '=', 'habilitacion.aula_id')
            ->leftJoin('usuarios as autor', 'autor.id', '=', 'habilitacion.registrada_por')
            ->orderBy('estudiante.apellidos')
            ->orderBy('estudiante.nombres')
            ->orderBy('estudiante.id')
            ->forPage($pagina, $porPagina)
            ->select([
                'estudiante.id',
                'estudiante.codigo_universitario',
                'estudiante.documento_identidad',
                'estudiante.nombres',
                'estudiante.apellidos',
                'lista.grupo',
                'habilitacion.id as habilitacion_id',
                'habilitacion.habilitado',
                'habilitacion.motivo',
                'habilitacion.updated_at as registrada_el',
                'autor.nombre as autor_nombre',
                'aula.nombre as aula_nombre',
            ])
            ->get();

        $inscritos = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $revisado = ($columnas['habilitacion_id'] ?? null) !== null;
            $habilitado = $revisado && $this->booleano($columnas['habilitado'] ?? null);
            $inhabilitado = $revisado && ! $habilitado;
            $momento = $this->texto($columnas['registrada_el'] ?? null);

            $inscritos[] = new InscritoData(
                estudianteId: $this->entero($columnas['id'] ?? null),
                codigo: $this->texto($columnas['codigo_universitario'] ?? null) ?? '',
                nombre: sprintf(
                    '%s, %s',
                    $this->texto($columnas['apellidos'] ?? null) ?? '',
                    $this->texto($columnas['nombres'] ?? null) ?? '',
                ),
                documento: $this->texto($columnas['documento_identidad'] ?? null),
                grupo: $this->texto($columnas['grupo'] ?? null) ?? '',
                estado: match (true) {
                    ! $revisado => FiltroHabilitacionData::PENDIENTE,
                    $habilitado => FiltroHabilitacionData::HABILITADO,
                    default => FiltroHabilitacionData::NO_HABILITADO,
                },
                aula: $this->texto($columnas['aula_nombre'] ?? null),
                motivo: $habilitado ? null : $this->texto($columnas['motivo'] ?? null),
                registradaPor: $inhabilitado ? $this->texto($columnas['autor_nombre'] ?? null) : null,
                registradaEl: $inhabilitado && $momento !== null ? substr($momento, 0, 16) : null,
            );
        }

        return $inscritos;
    }

    /**
     * @return array{todos: int, habilitado: int, no: int, pendiente: int}
     */
    public function condiciones(int $examenId, FiltroHabilitacionData $filtros): array
    {
        $fila = $this->filtrada($examenId, $filtros->sinCondicion())
            ->selectRaw(
                'count(*) as todos, '
                .'count(*) filter (where habilitacion.habilitado) as habilitados, '
                .'count(*) filter (where habilitacion.habilitado = false) as no_habilitados, '
                .'count(*) filter (where habilitacion.id is null) as pendientes'
            )
            ->first();

        $columnas = $fila === null ? [] : $this->columnas($fila);

        return [
            'todos' => $this->entero($columnas['todos'] ?? 0),
            'habilitado' => $this->entero($columnas['habilitados'] ?? 0),
            'no' => $this->entero($columnas['no_habilitados'] ?? 0),
            'pendiente' => $this->entero($columnas['pendientes'] ?? 0),
        ];
    }

    public function cifras(int $examenId): CifrasHabilitacionData
    {
        $fila = $this->base($examenId)
            ->selectRaw(
                'count(*) as inscritos, '
                .'count(*) filter (where habilitacion.habilitado) as habilitados, '
                .'count(*) filter (where habilitacion.habilitado = false) as no_habilitados, '
                .'count(*) filter (where habilitacion.id is null) as sin_revisar, '
                .'count(*) filter (where habilitacion.habilitado and habilitacion.aula_id is null) as sin_aula'
            )
            ->first();

        $columnas = $fila === null ? [] : $this->columnas($fila);

        return new CifrasHabilitacionData(
            inscritos: $this->entero($columnas['inscritos'] ?? 0),
            habilitados: $this->entero($columnas['habilitados'] ?? 0),
            noHabilitados: $this->entero($columnas['no_habilitados'] ?? 0),
            sinRevisar: $this->entero($columnas['sin_revisar'] ?? 0),
            sinAula: $this->entero($columnas['sin_aula'] ?? 0),
        );
    }

    /**
     * @return list<GrupoDeExamenData>
     */
    public function gruposDe(int $examenId, int $usuarioId): array
    {
        $filas = DB::table('examen_grupo as incluido')
            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
            ->leftJoin('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
            ->where('incluido.examen_id', $examenId)
            ->orderBy('grupo.codigo')
            ->orderBy('grupo.id')
            ->select(['grupo.id', 'grupo.codigo', 'docente.user_id'])
            ->get();

        $grupos = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $grupos[] = new GrupoDeExamenData(
                id: $this->entero($columnas['id'] ?? null),
                codigo: $this->texto($columnas['codigo'] ?? null) ?? '',
                propio: $this->enteroONulo($columnas['user_id'] ?? null) === $usuarioId,
            );
        }

        return $grupos;
    }

    /**
     * @return list<int>
     */
    public function estudiantesFiltrados(int $examenId, FiltroHabilitacionData $filtros): array
    {
        return $this->enteros(
            $this->filtrada($examenId, $filtros)->pluck('lista.estudiante_id')->all(),
        );
    }

    /**
     * @param  list<int>  $estudianteIds
     */
    public function cuantosNoInscritos(int $examenId, array $estudianteIds): int
    {
        if ($estudianteIds === []) {
            return 0;
        }

        $inscritos = 0;

        foreach (array_chunk($estudianteIds, self::LOTE) as $lote) {
            $inscritos += DB::table('examen_grupo as incluido')
                ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
                ->where('incluido.examen_id', $examenId)
                ->whereIn('inscripcion.estudiante_id', $lote)
                ->distinct()
                ->count('inscripcion.estudiante_id');
        }

        return count($estudianteIds) - $inscritos;
    }

    /**
     * @param  list<int>  $estudianteIds
     */
    public function cuantosConIngreso(int $examenId, array $estudianteIds): int
    {
        $conIngreso = 0;

        foreach (array_chunk($estudianteIds, self::LOTE) as $lote) {
            $conIngreso += DB::table('ingresos')
                ->where('examen_id', $examenId)
                ->whereIn('estudiante_id', $lote)
                ->count();
        }

        return $conIngreso;
    }

    /**
     * @param  list<int>  $estudianteIds
     */
    public function habilitar(int $examenId, array $estudianteIds, int $usuarioId): int
    {
        if ($estudianteIds === []) {
            return 0;
        }

        return $this->entero(DB::transaction(function () use ($examenId, $estudianteIds, $usuarioId): int {
            $yaHabilitados = [];

            foreach (array_chunk($estudianteIds, self::LOTE) as $lote) {
                $yaHabilitados = [
                    ...$yaHabilitados,
                    ...$this->enteros(
                        DB::table('habilitaciones')
                            ->where('examen_id', $examenId)
                            ->where('habilitado', true)
                            ->whereIn('estudiante_id', $lote)
                            ->pluck('estudiante_id')
                            ->all(),
                    ),
                ];
            }

            $porHabilitar = array_values(array_diff($estudianteIds, $yaHabilitados));

            if ($porHabilitar === []) {
                return 0;
            }

            // El aula no va entre las columnas que se actualizan: habilitar
            // no la toca (un no habilitado ya la tiene vacia).
            $this->guardar($examenId, $porHabilitar, true, null, $usuarioId, [
                'habilitado',
                'motivo',
                'registrada_por',
                'updated_at',
            ]);

            $cantidad = count($porHabilitar);

            $this->bitacora->registrar(
                $usuarioId,
                'habilitacion.habilitar',
                'habilitaciones',
                $examenId,
                sprintf('%d estudiante(s) habilitado(s) en el examen %d.', $cantidad, $examenId),
            );

            return $cantidad;
        }, 3));
    }

    /**
     * @param  list<int>  $estudianteIds
     */
    public function inhabilitar(int $examenId, array $estudianteIds, string $motivo, int $usuarioId): int
    {
        if ($estudianteIds === []) {
            return 0;
        }

        return $this->entero(DB::transaction(function () use ($examenId, $estudianteIds, $motivo, $usuarioId): int {
            $this->guardar($examenId, $estudianteIds, false, $motivo, $usuarioId, [
                'habilitado',
                'aula_id',
                'motivo',
                'registrada_por',
                'updated_at',
            ]);

            $cantidad = count($estudianteIds);

            $this->bitacora->registrar(
                $usuarioId,
                'habilitacion.inhabilitar',
                'habilitaciones',
                $examenId,
                sprintf('%d estudiante(s) inhabilitado(s) en el examen %d.', $cantidad, $examenId),
            );

            return $cantidad;
        }, 3));
    }

    /**
     * @param  list<int>  $estudianteIds
     * @param  list<string>  $actualiza
     */
    private function guardar(
        int $examenId,
        array $estudianteIds,
        bool $habilitado,
        ?string $motivo,
        int $usuarioId,
        array $actualiza,
    ): void {
        $ahora = now();

        foreach (array_chunk($estudianteIds, self::LOTE) as $lote) {
            DB::table('habilitaciones')->upsert(
                array_map(static fn (int $estudianteId): array => [
                    'examen_id' => $examenId,
                    'estudiante_id' => $estudianteId,
                    'habilitado' => $habilitado,
                    'aula_id' => null,
                    'motivo' => $motivo,
                    'registrada_por' => $usuarioId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ], $lote),
                ['examen_id', 'estudiante_id'],
                $actualiza,
            );
        }
    }

    /**
     * Los inscritos de los grupos del examen, una fila por estudiante (con
     * el grupo de menor codigo), unidos a su condicion si la tienen.
     */
    private function base(int $examenId): Builder
    {
        $lista = DB::table('examen_grupo as incluido')
            ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
            ->where('incluido.examen_id', $examenId)
            ->selectRaw(
                'distinct on (inscripcion.estudiante_id) '
                .'inscripcion.estudiante_id, grupo.id as grupo_id, grupo.codigo as grupo'
            )
            ->orderBy('inscripcion.estudiante_id')
            ->orderBy('grupo.codigo')
            ->orderBy('grupo.id');

        return DB::query()
            ->fromSub($lista, 'lista')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'lista.estudiante_id')
            ->leftJoin('habilitaciones as habilitacion', function (JoinClause $union) use ($examenId): void {
                $union
                    ->on('habilitacion.estudiante_id', '=', 'lista.estudiante_id')
                    ->where('habilitacion.examen_id', '=', $examenId);
            });
    }

    private function filtrada(int $examenId, FiltroHabilitacionData $filtros): Builder
    {
        $consulta = $this->base($examenId);

        if ($filtros->grupoId !== null) {
            $consulta->where('lista.grupo_id', $filtros->grupoId);
        }

        if ($filtros->aulaId !== null) {
            $consulta->where('habilitacion.aula_id', $filtros->aulaId);
        }

        match ($filtros->condicion) {
            FiltroHabilitacionData::HABILITADO => $consulta->where('habilitacion.habilitado', true),
            FiltroHabilitacionData::NO_HABILITADO => $consulta->where('habilitacion.habilitado', false),
            FiltroHabilitacionData::PENDIENTE => $consulta->whereNull('habilitacion.id'),
            default => null,
        };

        foreach ($this->palabras($filtros->buscar) as $palabra) {
            $consulta->whereRaw(
                "translate(lower(concat_ws(' ', estudiante.apellidos, estudiante.nombres, "
                .'estudiante.codigo_universitario, estudiante.documento_identidad)), ?, ?) like ?',
                [self::CON_TILDE, self::SIN_TILDE, '%'.addcslashes($palabra, '\\%_').'%'],
            );
        }

        return $consulta;
    }

    /**
     * Cada palabra tecleada tiene que aparecer en el nombre, el codigo o
     * el documento, en cualquier orden.
     *
     * @return list<string>
     */
    private function palabras(?string $buscar): array
    {
        if ($buscar === null) {
            return [];
        }

        $normalizado = strtr(mb_strtolower($buscar), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        $palabras = preg_split('/[\s,]+/u', $normalizado, -1, PREG_SPLIT_NO_EMPTY);

        return $palabras === false ? [] : $palabras;
    }

    /**
     * @param  array<array-key, mixed>  $valores
     * @return list<int>
     */
    private function enteros(array $valores): array
    {
        return array_values(array_map(fn (mixed $valor): int => $this->entero($valor), $valores));
    }

    private function instante(?string $marca): ?string
    {
        if ($marca === null) {
            return null;
        }

        // La base guarda la hora de la aplicacion (`config('umss.zona_horaria')`).
        return (new DateTimeImmutable($marca))->format(DATE_ATOM);
    }
}
