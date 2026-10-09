<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\ActualizarExamen;
use App\Modules\Examenes\Application\Actions\EliminarExamen;
use App\Modules\Examenes\Application\Actions\RegistrarExamen;
use App\Modules\Examenes\Application\DTOs\AulaDetalleData;
use App\Modules\Examenes\Application\DTOs\ExamenDetalleData;
use App\Modules\Examenes\Application\DTOs\ExamenResumenData;
use App\Modules\Examenes\Application\DTOs\GrupoDeExamenData;
use App\Modules\Examenes\Application\DTOs\NormaMarcadaData;
use App\Modules\Examenes\Application\Queries\ConsultarExamen;
use App\Modules\Examenes\Application\Queries\ListarExamenesDelDocente;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenConIngresosException;
use App\Modules\Examenes\Http\Requests\GuardarExamenRequest;
use App\Modules\Examenes\Http\Requests\ListarExamenesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ExamenController
{
    use RespuestasDeExamen;

    public function index(ListarExamenesRequest $request, ListarExamenesDelDocente $listar): JsonResponse
    {
        $listado = $listar->execute($this->usuarioId(), $request->periodo());

        return response()->json([
            'data' => array_map(
                fn (ExamenResumenData $examen): array => $this->resumen($examen),
                $listado->examenes,
            ),
            'meta' => [
                'periodo' => $listado->periodo,
                'hoy' => $listado->hoy,
                'hora_servidor' => $listado->horaServidor,
            ],
        ]);
    }

    public function tipos(): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn (TipoExamen $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                ],
                TipoExamen::cases(),
            ),
        ]);
    }

    public function show(int $examen, ConsultarExamen $consultar): JsonResponse
    {
        try {
            $detalle = $consultar->execute($examen, $this->usuarioId());
        } catch (ExamenAjenoException) {
            return $this->ajeno();
        }

        if ($detalle === null) {
            return $this->noEncontrado();
        }

        return response()->json(['data' => $this->detalle($detalle)]);
    }

    public function store(GuardarExamenRequest $request, RegistrarExamen $registrar): JsonResponse
    {
        try {
            $detalle = $registrar->execute($request->toData(), $this->usuarioId());
        } catch (DatoInvalidoException $error) {
            return $this->invalido($error);
        }

        if ($detalle === null) {
            return $this->noEncontrado();
        }

        return response()->json([
            'data' => $this->detalle($detalle),
            'message' => 'Examen registrado.',
        ], Response::HTTP_CREATED);
    }

    public function update(GuardarExamenRequest $request, int $examen, ActualizarExamen $actualizar): JsonResponse
    {
        try {
            $detalle = $actualizar->execute($examen, $request->toData(), $this->usuarioId());
        } catch (ExamenAjenoException) {
            return $this->ajeno();
        } catch (ExamenConIngresosException) {
            return $this->conIngresos(
                'El examen ya tiene ingresos registrados: solo se pueden cambiar las normas.',
            );
        } catch (DatoInvalidoException $error) {
            return $this->invalido($error);
        }

        if ($detalle === null) {
            return $this->noEncontrado();
        }

        return response()->json([
            'data' => $this->detalle($detalle),
            'message' => 'Examen guardado.',
        ]);
    }

    public function destroy(int $examen, EliminarExamen $eliminar): JsonResponse|Response
    {
        try {
            $eliminado = $eliminar->execute($examen, $this->usuarioId());
        } catch (ExamenAjenoException) {
            return $this->ajeno();
        } catch (ExamenConIngresosException) {
            return $this->conIngresos(
                'El examen ya tiene ingresos registrados: no se puede eliminar.',
            );
        }

        return $eliminado ? response()->noContent() : $this->noEncontrado();
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(ExamenResumenData $examen): array
    {
        return [
            'id' => $examen->id,
            'asignatura' => [
                'id' => $examen->asignaturaId,
                'codigo' => $examen->asignaturaCodigo,
                'nombre' => $examen->asignaturaNombre,
            ],
            'tipo' => $examen->tipo,
            'tipo_texto' => $examen->tipoTexto,
            'fecha' => $examen->fecha,
            'hora' => $examen->hora,
            'duracion' => $examen->duracion,
            'grupos' => $examen->grupos,
            'inscritos' => $examen->inscritos,
            'aulas' => $examen->aulas,
            'habilitados' => $examen->habilitados,
            'no_habilitados' => $examen->noHabilitados,
            'sin_revisar' => $examen->sinRevisar,
            'qr_emitidos' => $examen->qrEmitidos,
            'avance' => [
                'grupos' => $examen->avance->grupos,
                'aulas' => $examen->avance->aulas,
                'habilitacion' => $examen->avance->habilitacion,
                'qr' => $examen->avance->qr,
            ],
            'estado' => $examen->avance->estado,
            'accion' => [
                'clave' => $examen->avance->accion,
                'paso' => $examen->avance->paso,
            ],
            'propio' => $examen->propio,
            'registrado_por' => $examen->registradoPor,
            'junto_con' => $examen->juntoCon,
            'es_hoy' => $examen->esHoy,
            'rendido' => $examen->rendido,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(ExamenDetalleData $examen): array
    {
        return array_merge($this->resumen($examen->resumen), [
            'normas' => $examen->normas,
            'normas_marcadas' => array_map(
                static fn (NormaMarcadaData $norma): array => [
                    'id' => $norma->id,
                    'plantilla_id' => $norma->plantillaId,
                    'texto' => $norma->texto,
                ],
                $examen->normasMarcadas,
            ),
            'grupos_detalle' => array_map(
                static fn (GrupoDeExamenData $grupo): array => [
                    'id' => $grupo->id,
                    'codigo' => $grupo->codigo,
                    'docente' => $grupo->docente,
                    'inscritos' => $grupo->inscritos,
                    'propio' => $grupo->propio,
                ],
                $examen->grupos,
            ),
            'aulas_detalle' => array_map(
                static fn (AulaDetalleData $aula): array => [
                    'aula_id' => $aula->aulaId,
                    'nombre' => $aula->nombre,
                    'ubicacion' => $aula->ubicacion,
                    'edificio_id' => $aula->edificioId,
                ],
                $examen->aulas,
            ),
        ]);
    }
}
