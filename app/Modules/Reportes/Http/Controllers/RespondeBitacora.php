<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Controllers;

use App\Modules\Reportes\Application\DTOs\BitacoraExamenData;
use App\Modules\Reportes\Application\DTOs\ControlDeAula;
use App\Modules\Reportes\Application\DTOs\SucesoExamen;
use App\Modules\Reportes\Domain\Enums\GravedadIncidencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * La bitacora de un examen, igual para el docente y para el administrador.
 */
trait RespondeBitacora
{
    /**
     * @param  BitacoraExamenData|null  $bitacora  null si el examen no existe
     */
    private function bitacoraJson(?BitacoraExamenData $bitacora): JsonResponse
    {
        if ($bitacora === null) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            // Quien controlo cada aula.
            'aulas' => array_map(
                static fn (ControlDeAula $aula): array => [
                    'aula' => $aula->aula,
                    'personal' => $aula->personal,
                    'con_codigo' => $aula->conCodigo,
                    'controlo' => $aula->quienes(),
                    'ingresos' => $aula->ingresos,
                    'primer_ingreso' => $aula->primerIngreso,
                    'ultimo_ingreso' => $aula->ultimoIngreso,
                ],
                $bitacora->aulas,
            ),
            'data' => array_map(
                static fn (SucesoExamen $suceso): array => [
                    'instante' => $suceso->instante,
                    'categoria' => $suceso->categoria,
                    'titulo' => $suceso->titulo,
                    'detalle' => $suceso->detalle,
                    'aula' => $suceso->aula,
                    'estudiante' => $suceso->estudiante,
                    'codigo' => $suceso->codigo,
                    'por' => $suceso->por,
                    'gravedad' => $suceso->gravedad,
                    'gravedad_texto' => $suceso->gravedad === null
                        ? null
                        : GravedadIncidencia::from($suceso->gravedad)->etiqueta(),
                ],
                $bitacora->sucesos,
            ),
        ]);
    }
}
