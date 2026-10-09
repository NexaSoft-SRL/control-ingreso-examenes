<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\AjustePeriodoGateway;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use Illuminate\Support\Facades\DB;

final readonly class EloquentAjustePeriodoGateway implements AjustePeriodoGateway
{
    public function __construct(
        private BitacoraGateway $bitacora,
    ) {}

    public function ajustar(int $periodoId, string $fechaInicio, string $fechaFin, ?int $autorId): bool
    {
        return DB::transaction(function () use ($periodoId, $fechaInicio, $fechaFin, $autorId): bool {
            $periodo = Periodo::query()->lockForUpdate()->find($periodoId);

            if (! $periodo instanceof Periodo) {
                return false;
            }

            $periodo->forceFill([
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ajustado_por' => $autorId,
            ])->save();

            $this->bitacora->registrar(
                $autorId,
                'periodo.ajustar',
                'periodos',
                $periodo->id,
                "Período {$periodo->codigo}: del {$fechaInicio} al {$fechaFin}",
            );

            return true;
        }, 3);
    }
}
