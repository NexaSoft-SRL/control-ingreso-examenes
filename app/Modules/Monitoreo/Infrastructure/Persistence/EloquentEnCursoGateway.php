<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Infrastructure\Persistence;

use App\Modules\Monitoreo\Application\Contracts\EnCursoGateway;
use App\Modules\Monitoreo\Application\DTOs\AvanceDeAulaData;
use App\Modules\Monitoreo\Application\DTOs\AvanceEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\IncidenciaEnCursoData;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * El monitoreo no tiene tablas: lee las de habilitaciones, ingresos e
 * incidencias por nombre de tabla. La pantalla repite estas consultas cada
 * diez segundos, asi que ninguna cuesta una consulta por aula.
 *
 * Los instantes se guardan sin zona, en la de la aplicacion
 * (`America/La_Paz`); salen en ISO 8601 con su desfase.
 */
final class EloquentEnCursoGateway implements EnCursoGateway
{
    private const TRAMO_MINUTOS = 5;

    public function avance(int $examenId): AvanceEnCursoData
    {
        $asignados = $this->conteoPorAula(
            $this->habilitados($examenId)->whereNotNull('h.aula_id'),
            'h.aula_id',
        );
        $faltan = $this->conteoPorAula(
            $this->sinIngreso($this->habilitados($examenId)->whereNotNull('h.aula_id')),
            'h.aula_id',
        );

        $ingresos = DB::table('ingresos')
            ->where('examen_id', $examenId)
            ->whereNotNull('aula_id')
            ->groupBy('aula_id')
            ->select(['aula_id'])
            ->selectRaw('count(*) as cantidad, max(registrado_en) as ultimo')
            ->get();

        // Quienes entraron por la puerta de un aula que no era la suya, desde
        // cada lado: los que llegaron de otra y los que se fueron a otra.
        $cruzados = static fn (): Builder => DB::table('ingresos as ingreso')
            ->join('habilitaciones as h', static function (JoinClause $union): void {
                $union->on('h.examen_id', '=', 'ingreso.examen_id')
                    ->on('h.estudiante_id', '=', 'ingreso.estudiante_id');
            })
            ->where('ingreso.examen_id', $examenId)
            ->whereNotNull('h.aula_id')
            ->whereNotNull('ingreso.aula_id')
            ->whereColumn('h.aula_id', '<>', 'ingreso.aula_id');
        $deOtra = $this->conteoPorAula($cruzados(), 'ingreso.aula_id');
        $enOtra = $this->conteoPorAula($cruzados(), 'h.aula_id');

        $ingresados = [];
        $ultimos = [];

        foreach ($ingresos as $fila) {
            $columnas = $this->columnas($fila);
            $aulaId = $this->entero($columnas['aula_id'] ?? null);
            $ingresados[$aulaId] = $this->entero($columnas['cantidad'] ?? null);
            $ultimos[$aulaId] = $this->instante($columnas['ultimo'] ?? null);
        }

        $aulas = [];

        foreach (array_unique(array_merge(array_keys($asignados), array_keys($ingresados))) as $aulaId) {
            $aulas[$aulaId] = new AvanceDeAulaData(
                asignados: $asignados[$aulaId] ?? 0,
                ingresados: $ingresados[$aulaId] ?? 0,
                faltan: $faltan[$aulaId] ?? 0,
                ultimoRegistro: $ultimos[$aulaId] ?? null,
                deOtra: $deOtra[$aulaId] ?? 0,
                enOtra: $enOtra[$aulaId] ?? 0,
            );
        }

        return new AvanceEnCursoData(
            ingresaron: DB::table('ingresos')->where('examen_id', $examenId)->count(),
            faltan: $this->sinIngreso($this->habilitados($examenId))->count(),
            ultimoRegistro: $this->instante(
                DB::table('ingresos')->where('examen_id', $examenId)->max('registrado_en'),
            ),
            aulas: $aulas,
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

    public function incidencias(int $examenId): array
    {
        $incidencias = [];

        foreach ($this->consultaIncidencias()->where('i.examen_id', $examenId)->get() as $fila) {
            $incidencias[] = $this->comoIncidencia($fila);
        }

        return $incidencias;
    }

    /**
     * Los habilitados del examen que siguen inscritos en alguno de sus
     * grupos: los mismos que cuenta la lista del aula (HU-21).
     */
    private function habilitados(int $examenId): Builder
    {
        return DB::table('habilitaciones as h')
            ->where('h.examen_id', $examenId)
            ->where('h.habilitado', true)
            ->whereExists(function (Builder $inscrito): void {
                $inscrito->selectRaw('1')
                    ->from('inscripciones as inscripcion')
                    ->join('examen_grupo as incluido', 'incluido.grupo_id', '=', 'inscripcion.grupo_id')
                    ->whereColumn('inscripcion.estudiante_id', 'h.estudiante_id')
                    ->whereColumn('incluido.examen_id', 'h.examen_id');
            });
    }

    private function sinIngreso(Builder $habilitados): Builder
    {
        return $habilitados->whereNotExists(function (Builder $ingreso): void {
            $ingreso->selectRaw('1')
                ->from('ingresos as ingreso')
                ->whereColumn('ingreso.examen_id', 'h.examen_id')
                ->whereColumn('ingreso.estudiante_id', 'h.estudiante_id');
        });
    }

    /**
     * @return array<int, int>
     */
    private function conteoPorAula(Builder $consulta, string $columna): array
    {
        $filas = $consulta
            ->groupBy($columna)
            ->select([$columna.' as aula_id'])
            ->selectRaw('count(*) as cantidad')
            ->get();

        $conteo = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $conteo[$this->entero($columnas['aula_id'] ?? null)] = $this->entero($columnas['cantidad'] ?? null);
        }

        return $conteo;
    }

    /**
     * Las incidencias que se atienden: las que reporto una persona y los
     * ingresos repetidos que asienta la puerta (`incidencias`, HU-19).
     */
    private function consultaIncidencias(): Builder
    {
        return DB::table('incidencias as i')
            ->join('aulas as a', 'a.id', '=', 'i.aula_id')
            ->leftJoin('usuarios as reporta', 'reporta.id', '=', 'i.reportada_por')
            ->leftJoin('roles as r', 'r.id', '=', 'reporta.role_id')
            ->leftJoin('accesos_codigo_aula as acceso', 'acceso.id', '=', 'i.acceso_codigo_aula_id')
            ->leftJoin('estudiantes as e', 'e.id', '=', 'i.estudiante_id')
            ->leftJoin('usuarios as atiende', 'atiende.id', '=', 'i.atendida_por')
            ->where(static function (Builder $donde): void {
                $donde->where('i.origen', 'PERSONA')->orWhere('i.tipo', 'INGRESO_REPETIDO');
            })
            ->orderByDesc('i.ocurrida_en')
            ->orderByDesc('i.id')
            ->select([
                'i.id',
                'i.examen_id',
                'i.aula_id',
                'a.nombre as aula',
                'i.tipo',
                'i.detalle',
                'i.estudiante_texto',
                'e.nombres',
                'e.apellidos',
                'reporta.nombre as reportante',
                'r.name as rol_reportante',
                'acceso.nombre_colaborador as colaborador',
                'i.ocurrida_en as reportada_en',
                'atiende.nombre as atendida_por',
                'i.atendida_en',
            ]);
    }

    private function comoIncidencia(object $fila): IncidenciaEnCursoData
    {
        $columnas = $this->columnas($fila);
        $nombres = $this->texto($columnas['nombres'] ?? null);
        $apellidos = $this->texto($columnas['apellidos'] ?? null);

        return new IncidenciaEnCursoData(
            id: $this->entero($columnas['id'] ?? null),
            examenId: $this->entero($columnas['examen_id'] ?? null),
            aulaId: $this->entero($columnas['aula_id'] ?? null),
            aula: $this->texto($columnas['aula'] ?? null) ?? '',
            tipo: $this->texto($columnas['tipo'] ?? null) ?? '',
            detalle: $this->texto($columnas['detalle'] ?? null) ?? '',
            estudiante: $nombres === null && $apellidos === null
                ? $this->texto($columnas['estudiante_texto'] ?? null)
                : trim(($apellidos ?? '').' '.($nombres ?? '')),
            reportante: $this->texto($columnas['reportante'] ?? null)
                ?? $this->texto($columnas['colaborador'] ?? null) ?? '',
            rolReportante: $this->texto($columnas['rol_reportante'] ?? null)
                ?? ($this->texto($columnas['colaborador'] ?? null) === null ? '' : 'Código de aula'),
            reportadaEn: $this->instante($columnas['reportada_en'] ?? null) ?? '',
            atendidaPor: $this->texto($columnas['atendida_por'] ?? null),
            atendidaEn: $this->instante($columnas['atendida_en'] ?? null),
        );
    }

    /**
     * `AAAA-MM-DD HH:MM:SS` en la zona de la aplicacion → ISO 8601.
     */
    private function instante(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        if ($texto === null) {
            return null;
        }

        return (new DateTimeImmutable(substr($texto, 0, 19), now()->getTimezone()))
            ->format(DATE_ATOM);
    }

    /**
     * @return array<string, mixed>
     */
    private function columnas(object $fila): array
    {
        $columnas = [];

        foreach (get_object_vars($fila) as $nombre => $valor) {
            $columnas[(string) $nombre] = $valor;
        }

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
