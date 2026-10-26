<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\IncidenciaIngresoGateway;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EloquentIncidenciaIngresoGateway implements IncidenciaIngresoGateway
{
    public function registrar(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        int $usuarioId,
        VeredictoIngreso $veredicto,
        string $detalle,
    ): void {
        if ($veredicto->permiteIngreso()) {
            throw new InvalidArgumentException(
                'No se registra una admisión como incidencia de rechazo.'
            );
        }

        $detalle = trim($detalle);

        if ($detalle === '') {
            throw new InvalidArgumentException(
                'El detalle de la incidencia es obligatorio.'
            );
        }

        DB::table('incidencias_ingreso')->insert([
            'examen_id' => $examenId,
            'aula_id' => $aulaId,
            'estudiante_id' => $estudianteId,
            'registrado_por' => $usuarioId,
            'tipo_rechazo' => $veredicto->value,
            'detalle' => $detalle,
            'registrado_en' => DB::raw('CURRENT_TIMESTAMP'),
        ]);
    }

    public function registrarIntento(
        int $aulaId,
        ?int $estudianteId,
        int $usuarioId,
        CausaIntento $causa,
        string $detalle,
    ): void {
        $detalle = trim($detalle);

        if ($detalle === '') {
            throw new InvalidArgumentException(
                'El detalle de la incidencia es obligatorio.'
            );
        }

        DB::table('incidencias_ingreso')->insert([
            'examen_id' => null,
            'aula_id' => $aulaId,
            'estudiante_id' => $estudianteId,
            'registrado_por' => $usuarioId,
            'tipo_rechazo' => $causa->value,
            'detalle' => $detalle,
            'registrado_en' => DB::raw('CURRENT_TIMESTAMP'),
        ]);
    }

    public function repetidoReciente(
        int $aulaId,
        ?int $estudianteId,
        string $tipo,
        string $detalle,
        int $minutos,
    ): bool {
        $desde = now()->subMinutes($minutos)->format('Y-m-d H:i:s');

        return DB::table('incidencias_ingreso')
            ->where('aula_id', $aulaId)
            ->where('tipo_rechazo', $tipo)
            ->where('detalle', trim($detalle))
            ->where(static function (Builder $consulta) use ($estudianteId): void {
                $estudianteId === null
                    ? $consulta->whereNull('estudiante_id')
                    : $consulta->where('estudiante_id', $estudianteId);
            })
            ->where('registrado_en', '>=', $desde)
            ->exists();
    }
}
