<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Http\Controllers;

use App\Modules\Monitoreo\Application\Actions\AtenderIncidencia;
use App\Modules\Monitoreo\Application\DTOs\AulaEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\ExamenEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\IncidenciaEnCursoData;
use App\Modules\Monitoreo\Application\Queries\ConsultarEnCurso;
use App\Modules\Monitoreo\Application\Queries\ListarEnCurso;
use App\Modules\Monitoreo\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Monitoreo\Domain\Exceptions\ExamenNoEncontradoException;
use App\Modules\Monitoreo\Domain\Exceptions\IncidenciaNoEncontradaException;
use App\Modules\Monitoreo\Domain\Exceptions\IncidenciaYaAtendidaException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * HU-22: el examen en curso, aula por aula. Las lecturas no escriben nada;
 * la unica escritura es marcar una incidencia como atendida.
 */
final class EnCursoController
{
    public function index(ListarEnCurso $listar): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn (ExamenEnCursoData $examen): array => $this->examen($examen),
                $listar->ejecutar($this->usuarioId()),
            ),
        ]);
    }

    public function show(int $examen, ConsultarEnCurso $consultar): JsonResponse
    {
        try {
            $tablero = $consultar->ejecutar($this->usuarioId(), $examen);
        } catch (ExamenNoEncontradoException $error) {
            return $this->error($error->getMessage(), 404);
        } catch (ExamenAjenoException $error) {
            return $this->error($error->getMessage(), 403);
        }

        return response()->json([
            'data' => [
                'examen' => $this->examen($tablero->examen),
                'hora_servidor' => $tablero->horaServidor,
                'ultimo_registro' => $tablero->avance->ultimoRegistro,
                'cifras' => [
                    'ingresaron' => $tablero->avance->ingresaron,
                    'faltan' => $tablero->avance->faltan,
                    'aulas' => count($tablero->aulas),
                    'incidencias_sin_atender' => $tablero->incidenciasSinAtender(),
                ],
                'aulas' => array_map(
                    fn (AulaEnCursoData $aula): array => $this->aula($aula),
                    $tablero->aulas,
                ),
                'incidencias' => array_map(
                    fn (IncidenciaEnCursoData $incidencia): array => $this->incidencia($incidencia),
                    $tablero->incidencias,
                ),
            ],
        ]);
    }

    public function atender(int $examen, int $incidencia, AtenderIncidencia $atender): JsonResponse
    {
        try {
            $atendida = $atender->ejecutar($this->usuarioId(), $examen, $incidencia);
        } catch (ExamenAjenoException $error) {
            return $this->error($error->getMessage(), 403);
        } catch (IncidenciaNoEncontradaException $error) {
            return $this->error($error->getMessage(), 404);
        } catch (IncidenciaYaAtendidaException $error) {
            return $this->error($error->getMessage(), 409);
        }

        return response()->json([
            'data' => $this->incidencia($atendida),
            'message' => 'Incidencia atendida.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function examen(ExamenEnCursoData $examen): array
    {
        return [
            'id' => $examen->id,
            'fecha' => $examen->fecha,
            'hora' => $examen->hora,
            'duracion_minutos' => $examen->duracion,
            'tipo' => $examen->tipo,
            'tipo_texto' => $examen->tipoTexto,
            'asignatura' => $examen->asignatura,
            'abierto' => $examen->abierto,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aula(AulaEnCursoData $aula): array
    {
        return [
            'aula_id' => $aula->aulaId,
            'nombre' => $aula->nombre,
            'ubicacion' => $aula->ubicacion,
            'edificio_id' => $aula->edificioId,
            'a_cargo' => $aula->aCargo === null ? null : $this->persona($aula->aCargo),
            'apoyo' => array_map(
                fn (array $persona): array => $this->persona($persona),
                $aula->apoyo,
            ),
            'asignados' => $aula->avance->asignados,
            'ingresados' => $aula->avance->ingresados,
            'faltan' => $aula->avance->faltan,
            'ultimo_registro' => $aula->avance->ultimoRegistro,
            'incidencias_sin_atender' => $aula->incidenciasSinAtender,
            'compartida' => $aula->compartida,
            'codigo_acceso' => $aula->codigoAcceso,
            'a_mi_cargo' => $aula->aMiCargo,
        ];
    }

    /**
     * @param  array{id: int, nombre: string, rol?: string}  $persona
     * @return array{id: int, nombre: string, es_docente: bool}
     */
    private function persona(array $persona): array
    {
        return [
            'id' => $persona['id'],
            'nombre' => $persona['nombre'],
            'es_docente' => ($persona['rol'] ?? '') === 'docente',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function incidencia(IncidenciaEnCursoData $incidencia): array
    {
        return [
            'id' => $incidencia->id,
            'aula_id' => $incidencia->aulaId,
            'aula' => $incidencia->aula,
            'tipo' => $incidencia->tipo,
            'detalle' => $incidencia->detalle,
            'estudiante' => $incidencia->estudiante,
            'reportante' => $incidencia->reportante,
            'rol_reportante' => $incidencia->rolReportante,
            'reportada_en' => $incidencia->reportadaEn,
            'atendida' => $incidencia->atendida(),
            'atendida_por' => $incidencia->atendidaPor,
            'atendida_en' => $incidencia->atendidaEn,
        ];
    }

    private function usuarioId(): int
    {
        $id = Auth::id();

        if (! is_int($id)) {
            throw new InvalidArgumentException('No hay una sesión activa.');
        }

        return $id;
    }

    private function error(string $mensaje, int $estado): JsonResponse
    {
        return response()->json(['message' => $mensaje], $estado);
    }
}
