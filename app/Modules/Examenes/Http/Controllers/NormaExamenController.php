<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\EliminarNormaExamen;
use App\Modules\Examenes\Application\Actions\ListarNormasExamen;
use App\Modules\Examenes\Application\Actions\RegistrarNormaExamen;
use App\Modules\Examenes\Http\Requests\RegistrarNormaExamenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use LogicException;

final class NormaExamenController
{
    public function index(int $examen, ListarNormasExamen $listar): JsonResponse
    {
        $normas = $listar->execute($examen);

        if ($normas === null) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $normas], Response::HTTP_OK);
    }

    public function paraEstudiante(
        int $examen,
        int $estudiante,
        ListarNormasExamen $listar,
    ): JsonResponse {
        $normas = $listar->paraEstudiante($examen, $estudiante);

        if ($normas === null) {
            return response()->json([
                'message' => 'Examen o estudiante no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $normas], Response::HTTP_OK);
    }

    public function store(
        RegistrarNormaExamenRequest $request,
        int $examen,
        RegistrarNormaExamen $registrar,
    ): JsonResponse {
        $norma = $registrar->execute(
            $examen,
            $request->toData(),
            $this->usuarioAutenticado(),
        );

        if ($norma === null) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $norma], Response::HTTP_CREATED);
    }

    public function destroy(
        int $examen,
        int $norma,
        EliminarNormaExamen $eliminar,
    ): JsonResponse|Response {
        $eliminada = $eliminar->execute(
            $examen,
            $norma,
            $this->usuarioAutenticado(),
        );

        if ($eliminada === null) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (! $eliminada) {
            return response()->json([
                'message' => 'Norma no encontrada para este examen.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->noContent();
    }

    private function usuarioAutenticado(): int
    {
        $user = Auth::guard('web')->user();

        if ($user === null) {
            throw new LogicException('No existe usuario autenticado.');
        }

        $id = $user->getKey();

        if (! is_int($id) && ! is_string($id)) {
            throw new LogicException(
                'El usuario autenticado no tiene un identificador válido.'
            );
        }

        return (int) $id;
    }
}
