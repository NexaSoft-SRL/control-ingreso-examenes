<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Queries\IdentificarEnPuerta;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class IdentificarEnPuertaController
{
    public function __invoke(
        Request $request,
        int $aula,
        IdentificarEnPuerta $identificar,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        /** @var array{tipo: string, identificador: string} $datos */
        $datos = $request->validate([
            'tipo' => ['required', 'string', Rule::in(['codigo', 'documento'])],
            'identificador' => [
                'required',
                'string',
                'regex:/^[0-9]{5,10}$/D',
            ],
        ]);

        try {
            $resultado = $identificar->ejecutar(
                $usuarioId,
                $aula,
                $datos['tipo'],
                $datos['identificador'],
                new DateTimeImmutable(
                    'now',
                    new DateTimeZone('America/La_Paz'),
                ),
            );
        } catch (AccesoPuntoDenegado $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => [
                'examen_id' => $resultado->examenId,
                'veredicto' => $resultado->veredicto?->value,
                'ambiguo' => $resultado->ambiguo,
                'admisible' => $resultado->puedeAdmitir(),
            ],
        ]);
    }
}
