<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\DTOs\IngresoData;
use App\Modules\Ingreso\Application\DTOs\NuevoIngresoData;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentIngresoGateway implements IngresoGateway
{
    use LeeFilas;

    private const UNICO = 'uq_ingreso_examen_estudiante';

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function deEstudiante(int $examenId, int $estudianteId): ?IngresoData
    {
        return $this->uno(
            $this->consulta()
                ->where('ingreso.examen_id', $examenId)
                ->where('ingreso.estudiante_id', $estudianteId),
        );
    }

    public function porId(int $ingresoId): ?IngresoData
    {
        return $this->uno($this->consulta()->where('ingreso.id', $ingresoId));
    }

    public function registrar(NuevoIngresoData $datos): ?IngresoData
    {
        try {
            /** @var int $id */
            $id = DB::transaction(function () use ($datos): int {
                $ahora = now();

                $id = DB::table('ingresos')->insertGetId([
                    'examen_id' => $datos->examenId,
                    'estudiante_id' => $datos->estudianteId,
                    'aula_id' => $datos->aulaId,
                    'medio' => $datos->medio->value,
                    'es_excepcion' => $datos->esExcepcion,
                    'registrado_en' => $ahora,
                    'registrado_por' => $datos->operador->usuarioId,
                    'acceso_aula_id' => $datos->operador->accesoAulaId,
                    'created_at' => $ahora,
                ]);

                $this->bitacora->registrar(
                    $datos->operador->usuarioId,
                    'ingreso.registrar',
                    'ingresos',
                    $id,
                    sprintf(
                        'Ingreso de %s a %s por el aula %s (%s)%s%s.',
                        $this->texto(DB::table('estudiantes')->where('id', $datos->estudianteId)->value('codigo_universitario')) ?? '—',
                        $this->texto(DB::table('examenes')
                            ->join('asignaturas', 'asignaturas.id', '=', 'examenes.asignatura_id')
                            ->where('examenes.id', $datos->examenId)
                            ->value('asignaturas.nombre')) ?? '—',
                        $this->texto(DB::table('aulas')->where('id', $datos->aulaId)->value('nombre')) ?? '—',
                        $datos->medio->etiqueta(),
                        $datos->esExcepcion ? ', por cambio de aula' : '',
                        $datos->operador->esAcceso() ? ', con código de aula' : '',
                    ),
                    $datos->operador->esAcceso() ? ($datos->operador->nombre ?? 'Código de aula') : null,
                );

                return $id;
            }, 3);
        } catch (QueryException $error) {
            // Dos puestos a la vez pasan la comprobacion previa: el unico
            // de la tabla deja entrar a uno solo.
            if ($this->esDuplicado($error)) {
                return null;
            }

            throw $error;
        }

        return $this->porId($id);
    }

    private function esDuplicado(QueryException $error): bool
    {
        return $error->getCode() === '23505'
            && str_contains($error->getMessage(), self::UNICO);
    }

    private function consulta(): Builder
    {
        return DB::table('ingresos as ingreso')
            ->join('aulas as aula', 'aula.id', '=', 'ingreso.aula_id')
            ->leftJoin('incidencias as expulsion', function (JoinClause $union): void {
                $union->on('expulsion.ingreso_id', '=', 'ingreso.id')
                    ->where('expulsion.tipo', '=', TipoIncidencia::Expulsion->value);
            })
            ->select([
                'ingreso.id',
                'ingreso.examen_id',
                'ingreso.estudiante_id',
                'ingreso.aula_id',
                'ingreso.registrado_en',
                'aula.nombre as aula',
                'expulsion.ocurrida_en as expulsado_en',
            ]);
    }

    private function uno(Builder $consulta): ?IngresoData
    {
        $fila = $consulta->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return new IngresoData(
            id: $this->entero($columnas['id'] ?? null),
            examenId: $this->entero($columnas['examen_id'] ?? null),
            estudianteId: $this->entero($columnas['estudiante_id'] ?? null),
            aulaId: $this->entero($columnas['aula_id'] ?? null),
            aula: $this->texto($columnas['aula'] ?? null) ?? '',
            hora: $this->hora($this->texto($columnas['registrado_en'] ?? null)) ?? '',
            expulsadoA: $this->hora($this->texto($columnas['expulsado_en'] ?? null), false),
        );
    }
}
