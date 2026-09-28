<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\CreateAmbiente;
use App\Modules\Administracion\Application\Actions\DeleteAmbiente;
use App\Modules\Administracion\Application\Actions\FindAmbiente;
use App\Modules\Administracion\Application\Actions\ListAmbientes;
use App\Modules\Administracion\Application\Actions\UpdateAmbiente;
use App\Modules\Administracion\Http\Requests\StoreAmbienteRequest;
use App\Modules\Administracion\Http\Requests\UpdateAmbienteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use LogicException;

final class AmbienteController
{
    public function __construct(
        private readonly CreateAmbiente $createAmbiente,
        private readonly UpdateAmbiente $updateAmbiente,
        private readonly DeleteAmbiente $deleteAmbiente,
        private readonly FindAmbiente $findAmbiente,
        private readonly ListAmbientes $listAmbientes,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            $this->listAmbientes->execute(),
            Response::HTTP_OK,
        );
    }

    public function store(StoreAmbienteRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        return response()->json(
            $this->createAmbiente->execute($data, $this->usuarioAutenticado()),
            Response::HTTP_CREATED,
        );
    }

    public function show(int $ambiente): JsonResponse
    {
        $found = $this->findAmbiente->execute($ambiente);

        if ($found === null) {
            return response()->json(
                ['message' => 'Ambiente no encontrado.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return response()->json($found, Response::HTTP_OK);
    }

    public function update(UpdateAmbienteRequest $request, int $ambiente): JsonResponse
    {
        $found = $this->findAmbiente->execute($ambiente);

        if ($found === null) {
            return response()->json(
                ['message' => 'Ambiente no encontrado.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        /** @var array<string, mixed> $data */
        $data = $request->validated();

        return response()->json(
            $this->updateAmbiente->execute($found, $data, $this->usuarioAutenticado()),
            Response::HTTP_OK,
        );
    }

    public function destroy(int $ambiente): JsonResponse
    {
        $found = $this->findAmbiente->execute($ambiente);

        if ($found === null) {
            return response()->json(
                ['message' => 'Ambiente no encontrado.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        $this->deleteAmbiente->execute($found, $this->usuarioAutenticado());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * La bitacora (HU-07) necesita saber quien hizo la operacion. Las rutas
     * de este controlador exigen sesion, asi que siempre hay usuario.
     */
    private function usuarioAutenticado(): int
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
