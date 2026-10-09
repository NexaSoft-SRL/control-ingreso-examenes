<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Las respuestas que repiten los controladores del modulo.
 */
trait RespuestasDeExamen
{
    protected function usuarioId(): int
    {
        $id = Auth::id();

        return is_int($id) ? $id : 0;
    }

    protected function noEncontrado(string $mensaje = 'Examen no encontrado.'): JsonResponse
    {
        return response()->json(['message' => $mensaje], Response::HTTP_NOT_FOUND);
    }

    protected function ajeno(): JsonResponse
    {
        return response()->json([
            'message' => 'Este examen no es tuyo.',
            'alcance' => true,
        ], Response::HTTP_FORBIDDEN);
    }

    protected function conIngresos(string $mensaje): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'codigo' => 'EXAMEN_CON_INGRESOS',
        ], Response::HTTP_CONFLICT);
    }

    protected function invalido(DatoInvalidoException $error): JsonResponse
    {
        return response()->json([
            'message' => $error->getMessage(),
            'errors' => [$error->campo => [$error->getMessage()]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
