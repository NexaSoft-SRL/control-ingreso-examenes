<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Controllers;

use App\Modules\Academico\Application\Actions\ActivarCuentaDocente;
use App\Modules\Academico\Application\Queries\ConsultarDocente;
use App\Modules\Academico\Application\Queries\ListarDocentes;
use App\Modules\Academico\Domain\Exceptions\DatoDeCuentaEnUsoException;
use App\Modules\Academico\Domain\Exceptions\DocenteYaTieneCuentaException;
use App\Modules\Academico\Http\Requests\ActivarCuentaDocenteRequest;
use App\Modules\Academico\Http\Requests\ListarDocentesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Rutas 30 a 32: docentes de la oferta y activacion de su cuenta.
 */
final class DocenteController
{
    public function index(ListarDocentesRequest $request, ListarDocentes $listar): JsonResponse
    {
        return response()->json($listar->execute($request->toData(), $request->pagina(20)));
    }

    public function show(int $docente, ConsultarDocente $consultar): JsonResponse
    {
        $detalle = $consultar->execute($docente);

        if ($detalle === null) {
            return $this->noEncontrado();
        }

        return response()->json(['data' => $detalle]);
    }

    public function activarCuenta(
        int $docente,
        ActivarCuentaDocenteRequest $request,
        ActivarCuentaDocente $activar,
    ): JsonResponse {
        $autorId = Auth::id();

        try {
            $cuenta = $activar->execute(
                $docente,
                $request->usuario(),
                $request->correo(),
                is_int($autorId) ? $autorId : null,
            );
        } catch (DocenteYaTieneCuentaException $error) {
            return response()->json([
                'message' => $error->getMessage(),
                'codigo' => 'YA_TIENE_CUENTA',
            ], Response::HTTP_CONFLICT);
        } catch (DatoDeCuentaEnUsoException $error) {
            return response()->json([
                'message' => $error->getMessage(),
                'errors' => [$error->campo => [$error->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($cuenta === null) {
            return $this->noEncontrado();
        }

        // La contrasena temporal se entrega esta unica vez.
        return response()->json([
            'data' => [
                'usuario' => $cuenta->usuario,
                'contrasena_temporal' => $cuenta->contrasenaTemporal,
                'enviada_a' => $cuenta->enviadaA,
                'caduca_en' => $cuenta->caducaEn,
            ],
            'message' => 'Cuenta activada.',
        ], Response::HTTP_CREATED);
    }

    private function noEncontrado(): JsonResponse
    {
        return response()->json([
            'message' => 'Docente no encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }
}
