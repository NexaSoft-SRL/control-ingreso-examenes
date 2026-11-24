<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\AnulacionIngresoGateway;
use App\Modules\Ingreso\Application\DTOs\IngresoAnulableData;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentAnulacionIngresoGateway implements AnulacionIngresoGateway
{
    use LeeFilas;

    private const UNICO = 'uq_anulacion_ingreso';

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function ingreso(int $ingresoId): ?IngresoAnulableData
    {
        $fila = DB::table('ingresos as ingreso')
            ->leftJoin('anulaciones_ingreso as anulacion', 'anulacion.ingreso_id', '=', 'ingreso.id')
            ->where('ingreso.id', $ingresoId)
            ->first(['ingreso.id', 'ingreso.examen_id', 'ingreso.aula_id', 'anulacion.id as anulacion_id']);

        if ($fila === null) {
            return null;
        }

        $c = $this->columnas($fila);

        return new IngresoAnulableData(
            id: $this->entero($c['id'] ?? null),
            examenId: $this->entero($c['examen_id'] ?? null),
            aulaId: $this->entero($c['aula_id'] ?? null),
            anulado: ($c['anulacion_id'] ?? null) !== null,
        );
    }

    public function anular(int $ingresoId, string $motivo, int $usuarioId): bool
    {
        try {
            DB::transaction(function () use ($ingresoId, $motivo, $usuarioId): void {
                $ahora = now();

                $id = DB::table('anulaciones_ingreso')->insertGetId([
                    'ingreso_id' => $ingresoId,
                    'motivo' => $motivo,
                    'anulado_por' => $usuarioId,
                    'anulado_en' => $ahora,
                ]);

                $datos = DB::table('ingresos as ingreso')
                    ->join('estudiantes as estudiante', 'estudiante.id', '=', 'ingreso.estudiante_id')
                    ->join('examenes as examen', 'examen.id', '=', 'ingreso.examen_id')
                    ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
                    ->where('ingreso.id', $ingresoId)
                    ->first(['estudiante.codigo_universitario as codigo', 'asignatura.nombre as asignatura']);

                $c = $datos === null ? [] : $this->columnas($datos);

                $this->bitacora->registrar(
                    $usuarioId,
                    'ingreso.anular',
                    'anulaciones_ingreso',
                    $id,
                    sprintf(
                        'Ingreso de %s a %s anulado: %s.',
                        $this->texto($c['codigo'] ?? null) ?? '—',
                        $this->texto($c['asignatura'] ?? null) ?? '—',
                        $motivo,
                    ),
                );
            }, 3);
        } catch (QueryException $error) {
            // Dos pantallas a la vez: el único deja asentar una sola.
            if ($error->getCode() === '23505' && str_contains($error->getMessage(), self::UNICO)) {
                return false;
            }

            throw $error;
        }

        return true;
    }
}
