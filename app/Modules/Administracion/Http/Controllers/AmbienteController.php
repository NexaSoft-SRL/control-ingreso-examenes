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
            $this->createAmbiente->execute($data),
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
            $this->updateAmbiente->execute($found, $data),
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

        $this->deleteAmbiente->execute($found);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
