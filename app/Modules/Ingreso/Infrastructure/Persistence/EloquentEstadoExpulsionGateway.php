<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Support\Facades\DB;

final class EloquentEstadoExpulsionGateway implements EstadoExpulsionGateway
{
    public function estaExpulsado(
        int $examenId,
        int $estudianteId,
    ): bool {
        return DB::table('incidencias as x')
            ->join('ingresos as i', 'i.id', '=', 'x.ingreso_id')
            ->where('x.tipo', TipoIncidencia::Expulsion->value)
            ->where('i.examen_id', $examenId)
            ->where('i.estudiante_id', $estudianteId)
            ->exists();
    }
}
