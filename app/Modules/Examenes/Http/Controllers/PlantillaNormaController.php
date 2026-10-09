<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\CrearPlantillaDeNorma;
use App\Modules\Examenes\Application\Actions\EditarPlantillaDeNorma;
use App\Modules\Examenes\Application\Actions\QuitarPlantillaDeNorma;
use App\Modules\Examenes\Application\DTOs\PlantillaNormaData;
use App\Modules\Examenes\Application\Queries\ListarPlantillasDeNormas;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Domain\Exceptions\PlantillaAjenaException;
use App\Modules\Examenes\Http\Requests\GuardarPlantillaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PlantillaNormaController
{
    use RespuestasDeExamen;

    public function index(ListarPlantillasDeNormas $listar): JsonResponse
    {
        $usuarioId = $this->usuarioId();

        return response()->json([
            'data' => array_map(
                fn (PlantillaNormaData $plantilla): array => $this->plantilla($plantilla, $usuarioId),
                $listar->execute($usuarioId),
            ),
        ]);
    }

    public function store(GuardarPlantillaRequest $request, CrearPlantillaDeNorma $crear): JsonResponse
    {
        $usuarioId = $this->usuarioId();

        try {
            $plantilla = $crear->execute($request->texto(), $usuarioId);
        } catch (DatoInvalidoException $error) {
            return $this->invalido($error);
        }

        return response()->json([
            'data' => $this->plantilla($plantilla, $usuarioId),
            'message' => 'Plantilla creada.',
        ], Response::HTTP_CREATED);
    }

    public function update(
        GuardarPlantillaRequest $request,
        int $plantilla,
        EditarPlantillaDeNorma $editar,
    ): JsonResponse {
        $usuarioId = $this->usuarioId();

        try {
            $guardada = $editar->execute($plantilla, $request->texto(), $usuarioId);
        } catch (PlantillaAjenaException $error) {
            return $this->plantillaAjena($error);
        } catch (DatoInvalidoException $error) {
            return $this->invalido($error);
        }

        if ($guardada === null) {
            return $this->noEncontrado('Plantilla no encontrada.');
        }

        return response()->json([
            'data' => $this->plantilla($guardada, $usuarioId),
            'message' => 'Plantilla guardada.',
        ]);
    }

    public function destroy(int $plantilla, QuitarPlantillaDeNorma $quitar): JsonResponse|Response
    {
        try {
            $quitada = $quitar->execute($plantilla, $this->usuarioId());
        } catch (PlantillaAjenaException $error) {
            return $this->plantillaAjena($error);
        }

        return $quitada ? response()->noContent() : $this->noEncontrado('Plantilla no encontrada.');
    }

    /**
     * @return array{id: int, texto: string, predefinida: bool, propia: bool}
     */
    private function plantilla(PlantillaNormaData $plantilla, int $usuarioId): array
    {
        return [
            'id' => $plantilla->id,
            'texto' => $plantilla->texto,
            'predefinida' => $plantilla->esPredefinida(),
            'propia' => $plantilla->esDe($usuarioId),
        ];
    }

    private function plantillaAjena(PlantillaAjenaException $error): JsonResponse
    {
        return response()->json([
            'message' => $error->getMessage(),
            'alcance' => true,
        ], Response::HTTP_FORBIDDEN);
    }
}
