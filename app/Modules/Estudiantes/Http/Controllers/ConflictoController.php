<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Estudiantes\Application\Actions\ResolverConflicto;
use App\Modules\Estudiantes\Application\Queries\ListarConflictos;
use App\Modules\Estudiantes\Domain\Enums\ResolucionConflicto;
use App\Modules\Estudiantes\Domain\Exceptions\ConflictoYaResueltoException;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;
use App\Modules\Estudiantes\Http\Requests\ListaRequest;
use App\Modules\Estudiantes\Http\Requests\ResolverConflictoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Los conflictos del padron y su resolucion (rutas 37 y 38).
 */
final class ConflictoController
{
    public function index(ListaRequest $request, ListarConflictos $listar): JsonResponse
    {
        $pagina = $listar->execute($request->toData(5));

        return response()->json([
            'data' => $pagina->filas,
            'meta' => $pagina->meta(),
        ]);
    }

    public function resolver(
        ResolverConflictoRequest $request,
        int $conflicto,
        ResolverConflicto $resolver,
    ): JsonResponse {
        $resolucion = $request->resolucion();

        try {
            $codigo = $resolver->execute($conflicto, $resolucion, (int) Auth::id());
        } catch (ConflictoYaResueltoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'codigo' => 'CONFLICTO_YA_RESUELTO',
            ], Response::HTTP_CONFLICT);
        } catch (DocumentoEnUsoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'codigo' => 'DOCUMENTO_EN_USO',
            ], Response::HTTP_CONFLICT);
        }

        if ($codigo === null) {
            return response()->json([
                'message' => 'Conflicto no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => $resolucion === ResolucionConflicto::UsarCarga
                ? "{$codigo} actualizado."
                : "{$codigo} sin cambios.",
        ]);
    }
}
