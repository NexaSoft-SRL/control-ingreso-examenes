<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Estudiantes\Application\Actions\CargarInscritos;
use App\Modules\Estudiantes\Application\DTOs\ConflictoCargaData;
use App\Modules\Estudiantes\Application\DTOs\RechazoCargaData;
use App\Modules\Estudiantes\Application\DTOs\ResultadoCargaData;
use App\Modules\Estudiantes\Application\DTOs\SolicitudCargaData;
use App\Modules\Estudiantes\Domain\Exceptions\ArchivoRechazadoException;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Domain\Exceptions\PeriodoCerradoException;
use App\Modules\Estudiantes\Http\Requests\CargarInscritosRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Las dos cargas de inscritos: las inscripciones de una facultad, que sube
 * la administracion (ruta 36), y la lista de un grupo, que sube su docente
 * (ruta 41). El archivo que no es una lista de inscritos se rechaza entero.
 */
final class CargaInscritosController
{
    public function __construct(
        private readonly CargarInscritos $cargar,
    ) {}

    public function facultad(CargarInscritosRequest $request): JsonResponse
    {
        $archivo = $request->archivo();

        if ($archivo === null) {
            return $this->sinArchivo();
        }

        return $this->procesar(
            SolicitudCargaData::deFacultad(
                $request->facultad(),
                (string) $archivo->getRealPath(),
                $archivo->getClientOriginalExtension(),
                $archivo->getClientOriginalName(),
                (int) Auth::id(),
            ),
            'Facultad no encontrada.',
        );
    }

    public function grupo(CargarInscritosRequest $request, int $grupo): JsonResponse
    {
        $archivo = $request->archivo();

        if ($archivo === null) {
            return $this->sinArchivo();
        }

        return $this->procesar(
            SolicitudCargaData::deGrupo(
                $grupo,
                (string) $archivo->getRealPath(),
                $archivo->getClientOriginalExtension(),
                $archivo->getClientOriginalName(),
                (int) Auth::id(),
            ),
            'Grupo no encontrado.',
        );
    }

    private function procesar(SolicitudCargaData $solicitud, string $noEncontrado): JsonResponse
    {
        try {
            $resultado = $this->cargar->execute($solicitud);
        } catch (GrupoAjenoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'alcance' => true,
            ], Response::HTTP_FORBIDDEN);
        } catch (PeriodoCerradoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'codigo' => 'PERIODO_CERRADO',
            ], Response::HTTP_CONFLICT);
        } catch (ArchivoRechazadoException $excepcion) {
            return $this->archivoRechazado($excepcion);
        }

        if ($resultado === null) {
            return response()->json(['message' => $noEncontrado], Response::HTTP_NOT_FOUND);
        }

        return response()->json($this->serializar($resultado));
    }

    /**
     * El archivo se rechazo entero y no se guardo nada: 422 con el codigo
     * del motivo y las columnas que se esperaban.
     */
    private function archivoRechazado(ArchivoRechazadoException $excepcion): JsonResponse
    {
        $cuerpo = [
            'message' => $excepcion->getMessage(),
            'codigo' => $excepcion->codigo,
            'orden_esperado' => $excepcion->ordenEsperado,
        ];

        if ($excepcion->codigo === ArchivoRechazadoException::FALTAN_COLUMNAS) {
            $cuerpo['columnas'] = $excepcion->columnas;
        }

        $cuerpo['errors'] = ['archivo' => [$excepcion->getMessage()]];

        return response()->json($cuerpo, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function sinArchivo(): JsonResponse
    {
        return response()->json([
            'message' => 'El archivo es obligatorio.',
            'errors' => ['archivo' => ['El archivo es obligatorio.']],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(ResultadoCargaData $resultado): array
    {
        return [
            'message' => 'Carga procesada.',
            'archivo' => $resultado->archivo,
            'resumen' => [
                'filas' => $resultado->filas,
                'nuevos' => $resultado->nuevos,
                'reutilizados' => $resultado->reutilizados,
                'ya_inscritos' => $resultado->yaInscritos,
                'rechazados' => count($resultado->rechazos),
                'conflictos' => count($resultado->conflictos),
            ],
            'rechazos' => array_map(
                static fn (RechazoCargaData $rechazo): array => [
                    'fila' => $rechazo->fila,
                    'motivo' => $rechazo->motivo,
                ],
                $resultado->rechazos,
            ),
            'conflictos' => array_map(
                static fn (ConflictoCargaData $conflicto): array => [
                    'fila' => $conflicto->fila,
                    'codigo' => $conflicto->codigo,
                    'motivo' => $conflicto->motivo,
                ],
                $resultado->conflictos,
            ),
        ];
    }
}
