<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Ingreso\Application\Actions\AtenderIncidencia;
use App\Modules\Ingreso\Application\Actions\CorregirIncidencia;
use App\Modules\Ingreso\Application\Actions\ReportarIncidencia;
use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Application\Queries\ListarIncidencias;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeFechaException;
use App\Modules\Ingreso\Domain\Exceptions\IncidenciaYaAtendidaException;
use App\Modules\Ingreso\Http\Requests\CorregirIncidenciaRequest;
use App\Modules\Ingreso\Http\Requests\ListarIncidenciasRequest;
use App\Modules\Ingreso\Http\Requests\ReportarIncidenciaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class IncidenciaController
{
    use Responde;

    public function index(
        int $examen,
        ListarIncidenciasRequest $request,
        ListarIncidencias $listar,
        PuertaGateway $puerta,
    ): JsonResponse {
        $datos = $puerta->examen($examen);
        $usuarioId = $this->usuarioId();

        if ($datos === null || $usuarioId === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            return response()->json($listar->execute(
                $datos,
                $usuarioId,
                $request->aulaId(),
                $request->soloSinAtender(),
            ));
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        }
    }

    public function store(
        int $examen,
        ReportarIncidenciaRequest $request,
        ReportarIncidencia $reportar,
        PuertaGateway $puerta,
    ): JsonResponse {
        $datos = $puerta->examen($examen);

        if ($datos === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            $incidencia = $reportar->execute(
                $datos,
                $request->aulaId(),
                $request->tipo(),
                $request->estudiante(),
                $request->detalle(),
                $this->operador($request),
                $request->evidencias(),
            );
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        } catch (FueraDeFechaException $error) {
            return $this->conflicto($error->getMessage(), 'FUERA_DE_FECHA');
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
        $datos = $incidencias->reportada($incidencia);
        $usuarioId = $this->usuarioId();
        $examenId = $datos['examen_id'] ?? null;

        if ($datos === null || $usuarioId === null || ! is_int($examenId)) {
            return $this->noEncontrado('Incidencia no encontrada.');
        }

        try {
            $atender->execute($incidencia, $examenId, $usuarioId);
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (IncidenciaYaAtendidaException $error) {
            return $this->conflicto($error->getMessage(), 'YA_ATENDIDA');
        }

        return response()->json(['message' => 'Incidencia atendida.']);
    }

    public function corregir(
        int $incidencia,
        CorregirIncidenciaRequest $request,
        CorregirIncidencia $corregir,
        IncidenciaGateway $incidencias,
    ): JsonResponse {
        $datos = $incidencias->reportada($incidencia);
        $usuarioId = $this->usuarioId();
        $examenId = $datos['examen_id'] ?? null;
        $actual = TipoIncidencia::tryFrom(is_string($datos['tipo'] ?? null) ? $datos['tipo'] : '');

        if ($datos === null || $usuarioId === null || ! is_int($examenId) || $actual === null) {
            return $this->noEncontrado('Incidencia no encontrada.');
        }

        try {
            $corregir->execute(
                $incidencia,
                $examenId,
                $actual,
                $request->tipo(),
                $request->detalle(),
                $request->evidencias(),
                $usuarioId,
                $request->cambiaEstudiante(),
                $request->estudiante(),
                is_string($datos['detalle'] ?? null) ? $datos['detalle'] : null,
            );
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        }

        $fila = $incidencias->reportada($incidencia);
        unset($fila['examen_id']);

        return response()->json(['data' => $fila, 'message' => 'Incidencia corregida.']);
    }

    /**
     * Una foto de la evidencia (la primera, o la `$numero`), para quien puede
     * ver la incidencia: el docente del examen o quien atiende su aula.
     */
    public function evidencia(
        int $incidencia,
        IncidenciaGateway $incidencias,
        PuertaGateway $puerta,
        AlcancePuesto $alcance,
        AlmacenImagenes $imagenes,
        int $numero = 1,
    ): JsonResponse|Response {
        $datos = $incidencias->reportada($incidencia);
        $usuarioId = $this->usuarioId();
        $examenId = $datos['examen_id'] ?? null;
        $aulaId = $datos['aula_id'] ?? null;
        $examen = is_int($examenId) ? $puerta->examen($examenId) : null;

        if ($datos === null || $usuarioId === null || $examen === null) {
            return $this->noEncontrado('Incidencia no encontrada.');
        }

        $puedeVer = $alcance->esDocente($usuarioId, $examen->id)
            || (is_int($aulaId) && $alcance->actuaEnExamen(new OperadorData($usuarioId), $examen, $aulaId));

        if (! $puedeVer) {
            return $this->fueraDeAlcance(FueraDeAlcanceException::aula());
        }

        $ruta = $incidencias->evidencia($incidencia, $numero);
        $jpeg = $ruta === null ? null : $imagenes->leer($ruta);

        if ($jpeg === null) {
            return $this->noEncontrado('Sin evidencia.');
        }

        return response($jpeg, Response::HTTP_OK, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
