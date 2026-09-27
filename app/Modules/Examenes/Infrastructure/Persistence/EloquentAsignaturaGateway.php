<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;
use App\Modules\Examenes\Application\DTOs\RegistrarAsignaturaData;
use App\Modules\Examenes\Domain\Exceptions\AsignaturaTieneDependenciasException;
use App\Modules\Examenes\Domain\Models\Asignatura;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use PDOException;

final class EloquentAsignaturaGateway implements AsignaturaGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function registrar(
        RegistrarAsignaturaData $data,
        int $usuarioId,
    ): Asignatura {
        /** @var Asignatura $asignatura */
        $asignatura = DB::transaction(
            function () use (
                $data,
                $usuarioId,
            ): Asignatura {
                $asignatura = Asignatura::query()->create([
                    'carrera_id' => 1,
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

                $asignaturaId = $asignatura->getKey();

                if (! is_int($asignaturaId)) {
                    throw new LogicException(
                        'El identificador de la asignatura no tiene el tipo esperado.'
                    );
                }

                $this->bitacora->registrar(
                    $usuarioId,
                    'asignatura.registrar',
                    'asignaturas',
                    $asignaturaId,
                    null,
                );

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

    public function eliminar(
        int $asignaturaId,
        int $usuarioId,
    ): bool {
        return DB::transaction(
            function () use (
                $asignaturaId,
                $usuarioId,
            ): bool {
                $asignatura = Asignatura::query()
                    ->lockForUpdate()
                    ->find($asignaturaId);

                if (! $asignatura instanceof Asignatura) {
                    return false;
                }

                try {
                    /*
                     * Los grupos pertenecen al agregado de la asignatura.
                     *
                     * Se eliminan explícitamente porque la FK actual
                     * grupos_asignatura -> asignaturas utiliza RESTRICT.
                     */
                    $asignatura->grupos()->delete();

                    $asignatura->delete();
                } catch (QueryException $exception) {
                    if ($this->isForeignKeyViolation($exception)) {
                        throw new AsignaturaTieneDependenciasException(
                            'La asignatura tiene registros asociados.',
                            previous: $exception,
                        );
                    }

                    throw $exception;
                }

                /*
                 * La auditoría forma parte de la misma transacción.
                 *
                 * Sus errores no se traducen como dependencias de la
                 * asignatura, pero cualquier fallo provoca rollback
                 * de toda la operación.
                 */
                $this->bitacora->registrar(
                    $usuarioId,
                    'asignatura.eliminar',
                    'asignaturas',
                    $asignaturaId,
                    null,
                );

                return true;
            },
            3
        );
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
