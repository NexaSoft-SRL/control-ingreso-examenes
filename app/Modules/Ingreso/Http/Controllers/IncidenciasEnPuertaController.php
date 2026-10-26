<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Contracts\DetallePuertaGateway;
use App\Modules\Ingreso\Application\Queries\ExamenesOperablesEnAula;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Incidencias de ingreso del aula: rechazos de sus examenes e intentos sin
 * examen del dia (no inscrito, sin registro, QR no valido; BUG-04).
 */
final readonly class IncidenciasEnPuertaController
{
    public function __invoke(
        Request $request,
        int $aula,
        ExamenesOperablesEnAula $operables,
        DetallePuertaGateway $detalle,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        $request->validate([
            'limite' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        if ($aula <= 0) {
            return response()->json([
                'message' => 'Aula no válida.',
            ], 422);
        }

        $fecha = (new DateTimeImmutable(
            'now',
            new DateTimeZone('America/La_Paz'),
        ))->format('Y-m-d');

        try {
            $autorizados = $operables->ejecutar($usuarioId, $aula, $fecha);
        } catch (AccesoPuntoDenegado $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 403);
        }

        $filas = $detalle->incidenciasDelAula(
            $aula,
            $autorizados,
            $fecha,
            $request->integer('limite', 20),
        );

        return response()->json([
            'data' => $filas,
            'meta' => ['cantidad' => count($filas)],
        ]);
    }
}
