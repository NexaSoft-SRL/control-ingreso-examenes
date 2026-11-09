<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use App\Modules\Ingreso\Http\Middleware\ExigirPuesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Las respuestas que repiten todos los controladores del modulo.
 */
trait Responde
{
    private function usuarioId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }

    /**
     * El operador que dejo el middleware `puesto`.
     */
    private function operador(Request $request): OperadorData
    {
        $operador = $request->attributes->get(ExigirPuesto::ATRIBUTO);

        if (! $operador instanceof OperadorData) {
            throw new LogicException('La ruta no pasa por el middleware de la puerta.');
        }

        return $operador;
    }

    private function noEncontrado(string $mensaje): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
        ], Response::HTTP_NOT_FOUND);
    }

    private function fueraDeAlcance(FueraDeAlcanceException $error): JsonResponse
    {
        return response()->json([
            'message' => $error->getMessage(),
            'alcance' => true,
        ], Response::HTTP_FORBIDDEN);
    }

    private function conflicto(string $mensaje, string $codigo): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'codigo' => $codigo,
        ], Response::HTTP_CONFLICT);
    }

    private function noValido(DatoNoValidoException $error): JsonResponse
    {
        return $this->campoNoValido($error->campo, $error->getMessage());
    }

    private function campoNoValido(string $campo, string $mensaje): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'errors' => [$campo => [$mensaje]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
