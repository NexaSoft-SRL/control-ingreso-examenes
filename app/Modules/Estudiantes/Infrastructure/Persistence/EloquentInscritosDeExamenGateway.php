<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Persistence;

use App\Modules\Estudiantes\Application\Contracts\InscritosDeExamenGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentInscritosDeExamenGateway implements InscritosDeExamenGateway
{
    use ConsultasDePadron;

    /**
     * @param  array<int>  $grupoIds
     * @return list<EstudianteData>
     */
    public function estudiantesDe(array $grupoIds): array
    {
        $grupoIds = array_values(array_unique($grupoIds));

        if ($grupoIds === []) {
            return [];
        }

        $filas = DB::table('estudiantes as e')
            ->where('e.activo', true)
            ->whereExists(function (Builder $inscrito) use ($grupoIds): void {
                $inscrito
                    ->selectRaw('1')
                    ->from('inscripciones as i')
                    ->whereColumn('i.estudiante_id', 'e.id')
                    ->whereIn('i.grupo_id', $grupoIds);
            })
            ->orderBy('e.apellidos')
            ->orderBy('e.nombres')
            ->orderBy('e.id')
            ->get(['e.id', 'e.codigo_universitario', 'e.documento_identidad', 'e.nombres', 'e.apellidos', 'e.verificado']);

        $estudiantes = [];

        foreach ($filas as $fila) {
            $estudiantes[] = $this->estudiante($fila);
        }

        return $estudiantes;
    }
}
