<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\AtenderIncidencia;
use App\Modules\Ingreso\Application\Actions\CorregirIncidencia;
use App\Modules\Ingreso\Application\Actions\ReportarIncidencia;
use App\Modules\Ingreso\Application\Authorization\AlcancePuerta;
use App\Modules\Ingreso\Application\Contracts\AlmacenEvidencias;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\Queries\ListarIncidencias;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\IncidenciaYaAtendidaException;
use App\Modules\Ingreso\Http\Middleware\ExigirPuesto;
use App\Modules\Ingreso\Http\Requests\CorregirIncidenciaRequest;
use App\Modules\Ingreso\Http\Requests\ListarIncidenciasRequest;
use App\Modules\Ingreso\Http\Requests\ReportarIncidenciaRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Las incidencias de un examen en un solo modelo: las reporta quien esta
 * en el aula (tambien con codigo de aula), las lista el docente o quien
 * atiende el aula, y el docente las atiende y las corrige.
 */
final class IncidenciaController
{
    public function index(
        int $examen,
        ListarIncidenciasRequest $request,
        ListarIncidencias $listar,
        IncidenciaGateway $incidencias,
    ): JsonResponse {
        if ($incidencias->fechaDelExamen($examen) === null) {
            return $this->error('Examen no encontrado.', Response::HTTP_NOT_FOUND);
        }

        try {
            return response()->json($listar->ejecutar(
                $examen,
                $this->usuarioId(),
                $request->aulaId(),
                $request->soloSinAtender(),
            ));
        } catch (AccesoPuntoDenegado $error) {
            return $this->error($error->getMessage(), Response::HTTP_FORBIDDEN);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        }
    }

    public function store(
        int $examen,
        ReportarIncidenciaRequest $request,
        ReportarIncidencia $reportar,
        IncidenciaGateway $incidencias,
    ): JsonResponse {
        if ($incidencias->fechaDelExamen($examen) === null) {
            return $this->error('Examen no encontrado.', Response::HTTP_NOT_FOUND);
        }

        try {
            $incidencia = $reportar->ejecutar(
                $examen,
                $request->aulaId(),
                $request->tipo(),
                $request->estudiante(),
                $request->detalle(),
                ExigirPuesto::operador($request),
                $request->evidencia(),
            );
        } catch (AccesoPuntoDenegado $error) {
            return $this->error($error->getMessage(), Response::HTTP_FORBIDDEN);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        } catch (DomainException $error) {
            return $this->error($error->getMessage(), Response::HTTP_CONFLICT);
        }

        return response()->json([
            'data' => $incidencia,
            'message' => 'Incidencia registrada.',
        ], Response::HTTP_CREATED);
    }

    public function atender(
        int $incidencia,
        AtenderIncidencia $atender,
        IncidenciaGateway $incidencias,
    ): JsonResponse {
        $examenId = $incidencias->reportada($incidencia)['examen_id'] ?? null;

        if (! is_int($examenId)) {
            return $this->error('Incidencia no encontrada.', Response::HTTP_NOT_FOUND);
        }

        try {
            $atender->ejecutar($incidencia, $examenId, $this->usuarioId());
        } catch (AccesoPuntoDenegado $error) {
            return $this->error($error->getMessage(), Response::HTTP_FORBIDDEN);
        } catch (IncidenciaYaAtendidaException $error) {
            return $this->error($error->getMessage(), Response::HTTP_CONFLICT);
        }

        $fila = $incidencias->reportada($incidencia);
        unset($fila['examen_id']);

        return response()->json(['data' => $fila, 'message' => 'Incidencia atendida.']);
    }

    public function corregir(
        int $incidencia,
        CorregirIncidenciaRequest $request,
        CorregirIncidencia $corregir,
        IncidenciaGateway $incidencias,
    ): JsonResponse {
        $datos = $incidencias->reportada($incidencia);
        $examenId = $datos['examen_id'] ?? null;
        $actual = TipoIncidencia::tryFrom(is_string($datos['tipo'] ?? null) ? $datos['tipo'] : '');

        if (! is_int($examenId) || $actual === null) {
            return $this->error('Incidencia no encontrada.', Response::HTTP_NOT_FOUND);
        }

        try {
            $corregir->ejecutar(
                $incidencia,
                $examenId,
                $actual,
                $request->tipo(),
                $request->detalle(),
                $request->evidencia(),
                $this->usuarioId(),
                $request->cambiaEstudiante(),
                $request->estudiante(),
                is_string($datos['detalle'] ?? null) ? $datos['detalle'] : null,
            );
        } catch (AccesoPuntoDenegado $error) {
            return $this->error($error->getMessage(), Response::HTTP_FORBIDDEN);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        }

        $fila = $incidencias->reportada($incidencia);
        unset($fila['examen_id']);

        return response()->json(['data' => $fila, 'message' => 'Incidencia corregida.']);
    }

    /**
     * La foto de la evidencia, para el docente del examen o quien atiende
     * su aula.
     */
    public function evidencia(
        int $incidencia,
        IncidenciaGateway $incidencias,
        AlcancePuerta $alcance,
        AlmacenEvidencias $almacen,
    ): JsonResponse|Response {
        $datos = $incidencias->reportada($incidencia);
        $examenId = $datos['examen_id'] ?? null;
        $aulaId = $datos['aula_id'] ?? null;

        if (! is_int($examenId)) {
            return $this->error('Incidencia no encontrada.', Response::HTTP_NOT_FOUND);
        }

        $usuarioId = $this->usuarioId();

        if (! $alcance->esDocente($usuarioId, $examenId)
            && ! (is_int($aulaId) && $alcance->atiendeAula($usuarioId, $examenId, $aulaId))) {
            return $this->error('Sin asignación en esta aula.', Response::HTTP_FORBIDDEN);
        }

        $ruta = $incidencias->evidencia($incidencia);
        $jpeg = $ruta === null ? null : $almacen->leer($ruta);

        if ($jpeg === null) {
            return $this->error('Sin evidencia.', Response::HTTP_NOT_FOUND);
        }

        return response($jpeg, Response::HTTP_OK, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function usuarioId(): int
    {
        return (int) Auth::id();
    }

    private function noValido(DatoNoValidoException $error): JsonResponse
    {
        return response()->json([
            'message' => $error->getMessage(),
            'errors' => [$error->campo => [$error->getMessage()]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function error(string $mensaje, int $estado): JsonResponse
    {
        return response()->json(['message' => $mensaje], $estado);
    }
}
