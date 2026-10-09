<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Http\Controllers;

use App\Modules\Habilitacion\Application\Actions\CambiarHabilitacion;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\AulaRepartoData;
use App\Modules\Habilitacion\Application\DTOs\CifrasHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\GrupoDeExamenData;
use App\Modules\Habilitacion\Application\DTOs\InscritoData;
use App\Modules\Habilitacion\Application\Queries\ListarHabilitacion;
use App\Modules\Habilitacion\Domain\Exceptions\EstudianteConIngresoException;
use App\Modules\Habilitacion\Domain\Exceptions\EstudianteNoInscritoException;
use App\Modules\Habilitacion\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Habilitacion\Http\Requests\CambiarHabilitacionRequest;
use App\Modules\Habilitacion\Http\Requests\ListarHabilitacionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class HabilitacionController
{
    public function index(
        ListarHabilitacionRequest $request,
        int $examen,
        ListarHabilitacion $listar,
        HabilitacionGateway $habilitaciones,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return $this->sinSesion();
        }

        if (! $habilitaciones->existeExamen($examen)) {
            return $this->noEncontrado();
        }

        try {
            $listado = $listar->execute(
                $examen,
                $request->filtros(),
                $request->pagina(),
                $request->porPagina(),
                $usuarioId,
            );
        } catch (ExamenAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        }

        return response()->json([
            'data' => array_map(
                static fn (InscritoData $inscrito): array => [
                    'estudiante_id' => $inscrito->estudianteId,
                    'codigo' => $inscrito->codigo,
                    'nombre' => $inscrito->nombre,
                    'documento' => $inscrito->documento,
                    'grupo' => $inscrito->grupo,
                    'estado' => $inscrito->estado,
                    'aula' => $inscrito->aula,
                    'motivo' => $inscrito->motivo,
                ],
                $listado->filas,
            ),
            'meta' => [
                'total' => $listado->total,
                'pagina' => $listado->pagina,
                'por_pagina' => $listado->porPagina,
                'cifras' => $this->cifras($listado->cifras),
                'por_aula' => array_map(
                    static fn (AulaRepartoData $aula): array => [
                        'aula_id' => $aula->aulaId,
                        'nombre' => $aula->nombre,
                        'asignados' => $aula->asignados,
                    ],
                    $listado->porAula,
                ),
                'condiciones' => $listado->condiciones,
                'grupos' => array_map(
                    static fn (GrupoDeExamenData $grupo): array => [
                        'id' => $grupo->id,
                        'codigo' => $grupo->codigo,
                        'propio' => $grupo->propio,
                    ],
                    $listado->grupos,
                ),
            ],
        ]);
    }

    public function store(
        CambiarHabilitacionRequest $request,
        int $examen,
        CambiarHabilitacion $cambiar,
        HabilitacionGateway $habilitaciones,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return $this->sinSesion();
        }

        if (! $habilitaciones->existeExamen($examen)) {
            return $this->noEncontrado();
        }

        try {
            $resultado = $cambiar->execute($examen, $request->toData(), $usuarioId);
        } catch (ExamenAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        } catch (EstudianteNoInscritoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'errors' => ['estudiantes' => [$excepcion->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (EstudianteConIngresoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'codigo' => 'ESTUDIANTE_CON_INGRESO',
            ], Response::HTTP_CONFLICT);
        }

        $palabra = $resultado->habilitado ? 'habilitado' : 'inhabilitado';

        return response()->json([
            'message' => sprintf(
                '%d %s.',
                $resultado->afectados,
                $resultado->afectados === 1 ? $palabra : $palabra.'s',
            ),
            'afectados' => $resultado->afectados,
            'cifras' => $this->cifras($resultado->cifras),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function cifras(CifrasHabilitacionData $cifras): array
    {
        return [
            'inscritos' => $cifras->inscritos,
            'habilitados' => $cifras->habilitados,
            'no_habilitados' => $cifras->noHabilitados,
            'sin_revisar' => $cifras->sinRevisar,
            'sin_aula' => $cifras->sinAula,
        ];
    }

    private function sinSesion(): JsonResponse
    {
        return response()->json([
            'message' => 'No hay una sesión activa.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function noEncontrado(): JsonResponse
    {
        return response()->json([
            'message' => 'Examen no encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }

    private function ajeno(ExamenAjenoException $excepcion): JsonResponse
    {
        return response()->json([
            'message' => $excepcion->getMessage(),
            'alcance' => true,
        ], Response::HTTP_FORBIDDEN);
    }
}
