<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;
use App\Modules\Examenes\Application\DTOs\RegistrarAsignaturaData;
use App\Modules\Examenes\Domain\Exceptions\AsignaturaTieneDependenciasException;
use App\Modules\Examenes\Domain\Models\Asignatura;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;

final class EloquentAsignaturaGateway implements AsignaturaGateway
{
    public function registrar(
        RegistrarAsignaturaData $data,
    ): Asignatura {
        /** @var Asignatura $asignatura */
        $asignatura = DB::transaction(
            function () use ($data): Asignatura {
                $asignatura = Asignatura::query()->create([
                    'codigo' => $data->codigo,
                    'nombre' => $data->nombre,
                    'semestre' => $data->semestre,
                    'descripcion' => $data->descripcion,
                    'estado' => true,
                ]);

                foreach ($data->grupos as $grupo) {
                    $asignatura->grupos()->create([
                        'docente_id' => $grupo->docenteId,
                        'codigo_grupo' => $grupo->codigoGrupo,
                        'cupo' => $grupo->cupo,
                    ]);
                }

                return $asignatura;
            },
            3
        );

        $asignatura->load([
            'grupos.docente',
        ]);

        return $asignatura;
    }

    /**
     * @return list<Asignatura>
     */
    public function listar(): array
    {
        $asignaturas = Asignatura::query()
            ->with([
                'grupos.docente',
            ])
            ->orderBy('codigo')
            ->get()
            ->all();

        return array_values($asignaturas);
    }

    public function eliminar(int $asignaturaId): bool
    {
        try {
            return DB::transaction(
                function () use ($asignaturaId): bool {
                    $asignatura = Asignatura::query()
                        ->lockForUpdate()
                        ->find($asignaturaId);

                    if (! $asignatura instanceof Asignatura) {
                        return false;
                    }

                    /*
                     * Los grupos pertenecen al agregado de la asignatura.
                     *
                     * Se eliminan explícitamente porque la FK actual
                     * grupos_asignatura -> asignaturas utiliza RESTRICT.
                     *
                     * Todo permanece dentro de la misma transacción para
                     * impedir eliminaciones parciales.
                     */
                    $asignatura->grupos()->delete();

                    $asignatura->delete();

                    return true;
                },
                3
            );
        } catch (QueryException $exception) {
            if ($this->isForeignKeyViolation($exception)) {
                throw new AsignaturaTieneDependenciasException(
                    'La asignatura tiene registros asociados.',
                    previous: $exception,
                );
            }

            throw $exception;
        }
    }

    private function isForeignKeyViolation(
        QueryException $exception,
    ): bool {
        $previous = $exception->getPrevious();

        if (! $previous instanceof PDOException) {
            return false;
        }

        $sqlState = $previous->errorInfo[0] ?? null;

        return $sqlState === '23503';
    }
}
