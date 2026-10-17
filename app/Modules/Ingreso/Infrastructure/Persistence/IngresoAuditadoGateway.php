<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class IngresoAuditadoGateway implements IngresoGateway
{
    public function __construct(
        private EloquentIngresoGateway $ingresos,
        private BitacoraGateway $bitacora,
    ) {}

    public function yaIngreso(int $examenId, int $estudianteId): bool
    {
        return $this->ingresos->yaIngreso($examenId, $estudianteId);
    }

    public function registrar(
        int $examenId,
        int $estudianteId,
        int $aulaId,
        ?int $usuarioId = null,
        ?int $accesoCodigoAulaId = null,
    ): bool {
        return DB::transaction(function () use (
            $examenId,
            $estudianteId,
            $aulaId,
            $usuarioId,
            $accesoCodigoAulaId,
        ): bool {
            $registrado = $this->ingresos->registrar(
                $examenId,
                $estudianteId,
                $aulaId,
                $usuarioId,
                $accesoCodigoAulaId,
            );

            if (! $registrado) {
                return false;
            }

            $id = DB::table('ingresos')
                ->where('examen_id', $examenId)
                ->where('estudiante_id', $estudianteId)
                ->value('id');

            if (! is_int($id)) {
                throw new LogicException(
                    'No se pudo recuperar el ingreso registrado.',
                );
            }

            $detalle = $accesoCodigoAulaId !== null
                ? sprintf('Examen %d; estudiante %d; aula %d; acceso código aula %d.', $examenId, $estudianteId, $aulaId, $accesoCodigoAulaId)
                : sprintf('Examen %d; estudiante %d; aula %d.', $examenId, $estudianteId, $aulaId);

            $this->bitacora->registrar(
                $usuarioId,
                'INGRESO_ADMITIDO',
                'ingresos',
                $id,
                $detalle,
            );

            return true;
        });
    }
}
