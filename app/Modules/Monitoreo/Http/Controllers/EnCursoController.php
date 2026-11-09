<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Http\Controllers;

use App\Modules\Monitoreo\Application\DTOs\AulaEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\AulaSeguidaData;
use App\Modules\Monitoreo\Application\DTOs\ExamenEnCursoData;
use App\Modules\Monitoreo\Application\Queries\ConsultarEnCurso;
use App\Modules\Monitoreo\Application\Queries\ListarEnCurso;
use App\Modules\Monitoreo\Domain\Exceptions\SinAlcanceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class EnCursoController
{
    public function index(ListarEnCurso $listar): JsonResponse
    {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return $this->sinSesion();
        }

        return response()->json([
            'data' => array_map(
                fn (ExamenEnCursoData $examen): array => [
                    'examen_id' => $examen->examenId,
                    'texto' => $examen->texto,
                    'asignatura' => $examen->asignatura,
                    'tipo' => $examen->tipo,
                    'tipo_texto' => $examen->tipoTexto,
                    'fecha' => $examen->fecha,
                    'hora' => $examen->hora,
                    'abierto' => $examen->abierto,
                    'aulas' => array_map(
                        fn (AulaSeguidaData $aula): array => [
                            'aula_id' => $aula->id,
                            'nombre' => $aula->nombre,
                        ],
                        $examen->aulas,
                    ),
                ],
                $listar->execute($usuarioId),
            ),
        ]);
    }

    public function show(int $examen, ConsultarEnCurso $consultar): JsonResponse
    {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return $this->sinSesion();
        }

        try {
            $enCurso = $consultar->execute($usuarioId, $examen);
        } catch (SinAlcanceException $sinAlcance) {
            return response()->json([
                'message' => $sinAlcance->getMessage(),
                'alcance' => true,
            ], Response::HTTP_FORBIDDEN);
        }

        if ($enCurso === null) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'examen' => [
                'id' => $enCurso->examen->id,
                'asignatura' => $enCurso->examen->asignatura,
                'tipo' => $enCurso->examen->tipo,
                'fecha' => $enCurso->examen->fecha,
                'hora' => $enCurso->examen->hora,
            ],
            'abierto' => $enCurso->examen->abierto,
            'fin' => $enCurso->examen->fin,
            'excedido' => $enCurso->examen->excedido,
            'hora_servidor' => $enCurso->examen->horaServidor,
            'ultimo_ingreso' => $enCurso->cifras->ultimoIngreso,
            'cifras' => [
                'ingresaron' => $enCurso->cifras->ingresaron,
                'faltan' => $enCurso->cifras->faltan,
                'aulas' => $enCurso->cifras->aulas,
                'incidencias_sin_atender' => $enCurso->cifras->incidenciasSinAtender,
            ],
            'llegadas' => $enCurso->cifras->llegadas,
            'aulas' => array_map(
                fn (AulaEnCursoData $aula): array => [
                    'aula_id' => $aula->aulaId,
                    'nombre' => $aula->nombre,
                    'edificio_id' => $aula->edificioId,
                    'encargado' => [
                        'nombre' => $aula->encargado,
                        'es_docente' => $aula->encargadoEsDocente,
                    ],
                    'apoyo' => $aula->apoyo,
                    'ingresados' => $aula->ingresados,
                    'asignados' => $aula->asignados,
                    'faltan' => $aula->faltan,
                    'de_otra' => $aula->deOtra,
                    'en_otra' => $aula->enOtra,
                    'incidencias_sin_atender' => $aula->incidenciasSinAtender,
                    'compartida' => $aula->compartida,
                    'usa_codigo' => $aula->usaCodigo,
                    'a_mi_cargo' => $aula->aMiCargo,
                    'ultimo_ingreso' => $aula->ultimoIngreso,
                ],
                $enCurso->aulas,
            ),
        ]);
    }

    private function sinSesion(): JsonResponse
    {
        return response()->json([
            'message' => 'No hay una sesión activa.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
