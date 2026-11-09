<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Http\Controllers;

use App\Modules\Monitoreo\Application\DTOs\ConsultaAsistenciaData;
use App\Modules\Monitoreo\Application\DTOs\FilaAsistenciaData;
use App\Modules\Monitoreo\Application\Queries\ConsultarAsistenciaDeAula;
use App\Modules\Monitoreo\Domain\Exceptions\AulaFueraDelExamenException;
use App\Modules\Monitoreo\Domain\Exceptions\SinAlcanceException;
use App\Modules\Monitoreo\Http\Requests\ConsultarAsistenciaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class AsistenciaController
{
    public function __invoke(
        ConsultarAsistenciaRequest $request,
        int $examen,
        int $aula,
        ConsultarAsistenciaDeAula $consultar,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $consulta = $request->toData($examen, $aula);

        try {
            $asistencia = $consultar->execute($usuarioId, $consulta);
        } catch (SinAlcanceException $sinAlcance) {
            return response()->json([
                'message' => $sinAlcance->getMessage(),
                'alcance' => true,
            ], Response::HTTP_FORBIDDEN);
        } catch (AulaFueraDelExamenException $sinAula) {
            return response()->json([
                'message' => $sinAula->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($asistencia === null) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        $ingresaron = $consulta->lista === ConsultaAsistenciaData::INGRESARON;

        return response()->json([
            'examen' => [
                'id' => $asistencia->examen->id,
                'asignatura' => $asistencia->examen->asignatura,
                'tipo' => $asistencia->examen->tipo,
                'fecha' => $asistencia->examen->fecha,
                'hora' => $asistencia->examen->hora,
            ],
            'aula' => [
                'id' => $asistencia->aula->id,
                'nombre' => $asistencia->aula->nombre,
            ],
            'abierto' => $asistencia->examen->abierto,
            'hora_servidor' => $asistencia->examen->horaServidor,
            'cifras' => [
                'ingresaron' => $asistencia->pagina->ingresaron,
                'faltan' => $asistencia->pagina->faltan,
                'asignados' => $asistencia->pagina->asignados,
                'en_otra_aula' => $asistencia->pagina->enOtraAula,
                'de_otra_aula' => $asistencia->pagina->deOtraAula,
            ],
            'data' => array_map(
                fn (FilaAsistenciaData $fila): array => [
                    'codigo' => $fila->codigo,
                    'nombre' => $fila->nombre,
                    'grupo' => $fila->grupo,
                ] + ($ingresaron ? [
                    'hora' => $fila->hora,
                    'expulsado' => $fila->expulsado,
                ] : []),
                $asistencia->pagina->filas,
            ),
            'meta' => [
                'total' => $asistencia->pagina->total,
                'pagina' => $consulta->pagina,
                'por_pagina' => $consulta->porPagina,
            ],
        ]);
    }
}
