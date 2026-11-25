<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Quien ya no esta inscrito en ningun grupo del examen deja de tener
 * habilitacion y codigo QR en el.
 */
trait DejaSinHabilitacionAQuienSale
{
    private function limpiarHabilitacionesDelExamen(int $examenId): void
    {
        foreach (['habilitaciones', 'codigos_qr'] as $tabla) {
            DB::table($tabla)
                ->where($tabla.'.examen_id', $examenId)
                ->whereNotExists(function (Builder $inscrito) use ($examenId, $tabla): void {
                    $inscrito->selectRaw('1')
                        ->from('examen_grupo as incluido')
                        ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
                        ->where('incluido.examen_id', $examenId)
                        ->whereColumn('inscripcion.estudiante_id', $tabla.'.estudiante_id')
                        ->whereNull('inscripcion.baja_en');
                })
                ->delete();
        }
    }
}
