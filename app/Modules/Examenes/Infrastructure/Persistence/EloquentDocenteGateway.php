<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\DocenteGateway;
use App\Modules\Examenes\Application\DTOs\RegistrarDocenteData;
use App\Modules\Examenes\Domain\Models\Docente;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentDocenteGateway implements DocenteGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    /**
     * @return list<Docente>
     */
    public function listarActivos(): array
    {
        $docentes = Docente::query()
            ->where('estado', true)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get()
            ->all();

        return array_values($docentes);
    }

    public function registrar(
        RegistrarDocenteData $data,
        int $usuarioId,
    ): Docente {
        /** @var Docente $docente */
        $docente = DB::transaction(
            function () use (
                $data,
                $usuarioId,
            ): Docente {
                $docente = Docente::query()->create([
                    'user_id' => $data->cuentaId,
                    'codigo_docente' => $data->codigoDocente,
                    'nombres' => $data->nombres,
                    'apellidos' => $data->apellidos,
                    'correo' => $data->correo,
                    'telefono' => $data->telefono,
                    'estado' => true,
                ]);

                $docenteId = $docente->getKey();

                if (! is_int($docenteId)) {
                    throw new LogicException(
                        'El identificador del docente no tiene el tipo esperado.'
                    );
                }

                // HU-07 exige dejar rastro de las altas que hace el
                // administrador, no solo de las asignaturas.
                $this->bitacora->registrar(
                    $usuarioId,
                    'docente.registrar',
                    'docentes',
                    $docenteId,
                    null,
                );

                return $docente;
            },
            3
        );

        return $docente;
    }
}
