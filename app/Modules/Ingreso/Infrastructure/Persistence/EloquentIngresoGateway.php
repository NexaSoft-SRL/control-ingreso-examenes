<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentIngresoGateway implements IngresoGateway
{
    public function yaIngreso(int $examenId, int $estudianteId): bool
    {
        return DB::table('ingresos')
            ->where('examen_id', $examenId)
            ->where('estudiante_id', $estudianteId)
            ->exists();
    }

    public function registrar(
        int $examenId,
        int $estudianteId,
        int $aulaId,
        ?int $usuarioId = null,
        ?int $accesoCodigoAulaId = null,
    ): bool {
        try {
            DB::transaction(static function () use (
                $examenId,
                $estudianteId,
                $aulaId,
                $usuarioId,
                $accesoCodigoAulaId,
            ): void {
                DB::table('ingresos')->insert([
                    'examen_id' => $examenId,
                    'estudiante_id' => $estudianteId,
                    'aula_id' => $aulaId,
                    'registrado_por' => $usuarioId,
                    'acceso_codigo_aula_id' => $accesoCodigoAulaId,
                    'registrado_en' => DB::raw('CURRENT_TIMESTAMP'),
                ]);
            });

            return true;
        } catch (QueryException $error) {
            $estado = $error->errorInfo[0] ?? null;
            $mensaje = $error->errorInfo[2] ?? null;

            if (
                $estado === '23505'
                && is_string($mensaje)
                && str_contains(
                    $mensaje,
                    'uq_ingreso_examen_estudiante'
                )
            ) {
                return false;
            }

            throw $error;
        }
    }
}
