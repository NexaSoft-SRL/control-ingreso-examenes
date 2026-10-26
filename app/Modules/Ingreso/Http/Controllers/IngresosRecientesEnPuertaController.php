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
 * Ingresos del aula (BUG-12, BUG-23): los cinco ultimos o, con
 * `todos=1`, todos los del dia, con busqueda por nombre o codigo.
 */
final readonly class IngresosRecientesEnPuertaController
{
    private const RECIENTES = 5;

    private const TODOS = 500;

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

        if ($aula <= 0) {
            return response()->json([
                'message' => 'Aula no válida.',
            ], 422);
        }

        $request->validate([
            'todos' => ['sometimes', 'boolean'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

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

        $todos = $request->boolean('todos');
        $buscar = $todos ? $request->string('buscar')->trim()->toString() : '';

        $filas = $detalle->ingresosDelAula(
            $aula,
            $autorizados,
            $buscar === '' ? null : $buscar,
            $todos ? self::TODOS : self::RECIENTES,
        );

        return response()->json([
            'data' => $filas,
            'meta' => [
                'cantidad' => count($filas),
                'total' => $detalle->totalIngresos($aula, $autorizados),
            ],
        ]);
    }
}
