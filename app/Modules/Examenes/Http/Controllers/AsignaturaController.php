<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\EliminarAsignatura;
use App\Modules\Examenes\Application\Actions\ListarAsignaturas;
use App\Modules\Examenes\Application\Actions\RegistrarAsignatura;
use App\Modules\Examenes\Domain\Exceptions\AsignaturaTieneDependenciasException;
use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use App\Modules\Examenes\Domain\Models\GrupoAsignatura;
use App\Modules\Examenes\Http\Requests\RegistrarAsignaturaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use LogicException;

final class AsignaturaController
{
    public function index(
        ListarAsignaturas $listarAsignaturas,
    ): JsonResponse {
        $data = array_map(
            fn (Asignatura $asignatura): array => $this->serialize(
                $asignatura
            ),
            $listarAsignaturas->execute(),
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    public function store(
        RegistrarAsignaturaRequest $request,
        RegistrarAsignatura $registrarAsignatura,
    ): JsonResponse {
        $asignatura = $registrarAsignatura->execute(
            $request->toData(),
            $this->authenticatedUserId(),
        );

        return response()->json([
            'data' => $this->serialize($asignatura),
        ], Response::HTTP_CREATED);
    }

    public function destroy(
        int $asignatura,
        EliminarAsignatura $eliminarAsignatura,
    ): JsonResponse|Response {
        try {
            $eliminada = $eliminarAsignatura->execute(
                $asignatura,
                $this->authenticatedUserId(),
            );
        } catch (AsignaturaTieneDependenciasException) {
            return response()->json([
                'message' => 'No se puede eliminar la asignatura porque tiene registros asociados.',
            ], Response::HTTP_CONFLICT);
        }

        if (! $eliminada) {
            return response()->json([
                'message' => 'Asignatura no encontrada.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(
        Asignatura $asignatura,
    ): array {
        $grupos = $asignatura->grupos
            ->sortBy('codigo_grupo')
            ->values()
            ->map(
                static function (GrupoAsignatura $grupo): array {
                    $docente = $grupo->docente;

                    if (! $docente instanceof Docente) {
                        throw new LogicException(
                            'El grupo no tiene un docente responsable válido.'
                        );
                    }

                    return [
                        'id' => $grupo->getKey(),
                        'codigo_grupo' => $grupo->codigo_grupo,
                        'cupo' => $grupo->cupo,
                        'docente' => [
                            'id' => $docente->getKey(),
                            'codigo_docente' => $docente->codigo_docente,
                            'nombres' => $docente->nombres,
                            'apellidos' => $docente->apellidos,
                        ],
                    ];
                }
            )
            ->all();

        return [
            'id' => $asignatura->getKey(),
            'codigo' => $asignatura->codigo,
            'nombre' => $asignatura->nombre,
            'semestre' => $asignatura->semestre,
            'descripcion' => $asignatura->descripcion,
            'estado' => $asignatura->estado,
            'grupos' => $grupos,
        ];
    }

    private function authenticatedUserId(): int
{
    $user = Auth::guard('web')->user();

    if ($user === null) {
        throw new LogicException(
            'No existe un usuario autenticado.'
        );
    }

    return (int) $user->getKey();
}
}