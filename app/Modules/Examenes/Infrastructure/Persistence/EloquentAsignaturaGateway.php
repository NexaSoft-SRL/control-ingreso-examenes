<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;
use App\Modules\Examenes\Application\DTOs\RegistrarAsignaturaData;
use App\Modules\Examenes\Domain\Models\Asignatura;
use Illuminate\Support\Facades\DB;

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
}
