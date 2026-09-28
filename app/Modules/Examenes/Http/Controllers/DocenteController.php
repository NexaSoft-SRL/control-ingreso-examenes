<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\ListarDocentes;
use App\Modules\Examenes\Application\Actions\RegistrarDocente;
use App\Modules\Examenes\Domain\Models\Docente;
use App\Modules\Examenes\Http\Requests\RegistrarDocenteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use LogicException;

final class DocenteController
{
    public function index(
        ListarDocentes $listarDocentes,
    ): JsonResponse {
        $data = array_map(
            fn (Docente $docente): array => $this->serialize($docente),
            $listarDocentes->execute(),
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    public function store(
        RegistrarDocenteRequest $request,
        RegistrarDocente $registrarDocente,
    ): JsonResponse {
        $docente = $registrarDocente->execute(
            $request->toData(),
            $this->authenticatedUserId(),
        );

        return response()->json([
            'data' => $this->serialize($docente),
        ], Response::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(
        Docente $docente,
    ): array {
        return [
            'id' => $docente->getKey(),
            'codigo_docente' => $docente->codigo_docente,
            'nombres' => $docente->nombres,
            'apellidos' => $docente->apellidos,
        ];
    }

    private function authenticatedUserId(): int
    {
        $user = Auth::guard('web')->user();

        if ($user === null) {
            throw new LogicException(
                'No existe usuario autenticado.'
            );
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
