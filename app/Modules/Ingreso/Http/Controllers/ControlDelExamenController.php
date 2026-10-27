<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\ResolverAccesoDeAula;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Http\Requests\ResolverAccesoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * El panel en vivo del docente (HU-22): quien controla cada aula y los
 * accesos con codigo que esperan su autorizacion.
 */
final class ControlDelExamenController
{
    private const AVISOS = [
        'AUTORIZADO' => 'Acceso autorizado.',
        'RECHAZADO' => 'Acceso rechazado.',
        'CORTADO' => 'Acceso cortado.',
    ];

    public function resolver(
        int $examen,
        int $acceso,
        string $accion,
        ResolverAccesoRequest $request,
        ResolverAccesoDeAula $resolver,
    ): JsonResponse {
        $aula = $request->validated('aula_id');

        try {
            $estado = $resolver->ejecutar(
                $examen,
                $acceso,
                $accion,
                (int) Auth::id(),
                is_numeric($aula) ? (int) $aula : null,
            );
        } catch (AccesoPuntoDenegado $error) {
            return response()->json(['message' => $error->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (DatoNoValidoException $error) {
            return response()->json([
                'message' => $error->getMessage(),
                'errors' => [$error->campo => [$error->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($estado === null) {
            return response()->json(['message' => 'Acceso no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'estado' => mb_strtolower($estado),
            'message' => $accion === 'trasladar' && $estado === 'AUTORIZADO'
                ? 'Acceso movido de aula.'
                : (self::AVISOS[$estado] ?? 'El acceso ya estaba cerrado.'),
        ]);
    }
}
