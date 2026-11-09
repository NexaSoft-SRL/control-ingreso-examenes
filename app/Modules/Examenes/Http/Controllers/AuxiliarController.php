<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\QuitarAuxiliar;
use App\Modules\Examenes\Application\Actions\RegistrarAuxiliar;
use App\Modules\Examenes\Application\DTOs\AuxiliarData;
use App\Modules\Examenes\Application\Queries\ListarAuxiliares;
use App\Modules\Examenes\Domain\Exceptions\AuxiliarAsignadoException;
use App\Modules\Examenes\Domain\Exceptions\AuxiliarYaRegistradoException;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Http\Requests\ListarAuxiliaresRequest;
use App\Modules\Examenes\Http\Requests\RegistrarAuxiliarRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AuxiliarController
{
    use RespuestasDeExamen;

    public function index(ListarAuxiliaresRequest $request, ListarAuxiliares $listar): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn (AuxiliarData $auxiliar): array => $this->serializar(
                    $auxiliar->id,
                    $auxiliar->nombre,
                    $auxiliar->usuario,
                    $auxiliar->mio,
                ),
                $listar->execute($this->usuarioId(), $request->buscar()),
            ),
        ]);
    }

    public function store(RegistrarAuxiliarRequest $request, RegistrarAuxiliar $registrar): JsonResponse
    {
        try {
            $auxiliar = $registrar->execute(
                $request->nombre(),
                $request->codigoUniversitario(),
                $this->usuarioId(),
            );
        } catch (AuxiliarYaRegistradoException $existente) {
            return response()->json([
                'message' => 'Auxiliar ya registrado.',
                'data' => $this->serializar(
                    $existente->cuentaId,
                    $existente->nombre,
                    $existente->usuario,
                    $existente->mio,
                ),
            ], Response::HTTP_CONFLICT);
        } catch (DatoInvalidoException $error) {
            return $this->invalido($error);
        }

        return response()->json([
            'data' => $this->serializar($auxiliar->id, $auxiliar->nombre, $auxiliar->usuario, $auxiliar->mio),
            'message' => 'Auxiliar agregado.',
        ], Response::HTTP_CREATED);
    }

    public function destroy(int $auxiliar, QuitarAuxiliar $quitar): Response|JsonResponse
    {
        try {
            $quitado = $quitar->execute($auxiliar, $this->usuarioId());
        } catch (AuxiliarAsignadoException) {
            return response()->json([
                'message' => 'Atiende un examen pendiente.',
                'codigo' => 'AUXILIAR_ASIGNADO',
            ], Response::HTTP_CONFLICT);
        }

        return $quitado
            ? response()->noContent()
            : $this->noEncontrado('Auxiliar no encontrado.');
    }

    /**
     * @return array{id: int, nombre: string, usuario: string, mio: bool}
     */
    private function serializar(int $id, string $nombre, string $usuario, bool $mio): array
    {
        return [
            'id' => $id,
            'nombre' => $nombre,
            'usuario' => $usuario,
            'mio' => $mio,
        ];
    }
}
