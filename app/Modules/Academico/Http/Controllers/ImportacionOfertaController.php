<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Controllers;

use App\Modules\Academico\Application\Actions\ImportarOferta;
use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;
use App\Modules\Academico\Http\Requests\ImportarOfertaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Ruta 24: importa la oferta de una facultad (Importar / Reintentar).
 */
final class ImportacionOfertaController
{
    private const MINUTOS_EN_CURSO = 5;

    public function store(
        ImportarOfertaRequest $request,
        ConsultaOfertaGateway $oferta,
        ImportarOferta $importar,
    ): JsonResponse {
        $facultad = $request->facultad();

        if ($oferta->importacionEnCurso($facultad, self::MINUTOS_EN_CURSO)) {
            return response()->json([
                'message' => 'Ya hay una importación de esa facultad en curso.',
                'codigo' => 'IMPORTACION_EN_CURSO',
            ], Response::HTTP_CONFLICT);
        }

        // Corre dentro de la peticion: el servidor de destino no tiene
        // procesos en segundo plano.
        set_time_limit(120);

        $autorId = Auth::id();
        $resultado = $importar->execute($facultad, is_int($autorId) ? $autorId : null);

        if (! $resultado->importada) {
            return response()->json([
                'message' => "No se pudo importar {$resultado->sigla}.",
                'data' => [
                    'estado' => 'fallo',
                    'error' => $resultado->error,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => [
                'estado' => 'importada',
                'fecha' => $resultado->fechaFuente ?? $oferta->hoy(),
                'resumen' => $resultado->resumen(),
            ],
        ], Response::HTTP_CREATED);
    }
}
