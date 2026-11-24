<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Estudiantes\Application\Contracts\FotosDeEstudiantes;
use App\Modules\Ingreso\Application\Actions\AgregarFotoDeIntento;
use App\Modules\Ingreso\Application\Actions\AnularIngreso;
use App\Modules\Ingreso\Application\Actions\RegistrarExcepcion;
use App\Modules\Ingreso\Application\Actions\RegistrarExpulsion;
use App\Modules\Ingreso\Application\Actions\VerificarCarnetEnPuerta;
use App\Modules\Ingreso\Application\Actions\VerificarEnPuerta;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\Queries\ConsultarMapaDePuerta;
use App\Modules\Ingreso\Application\Queries\ConsultarPuerta;
use App\Modules\Ingreso\Application\Queries\ListarAulasDelPuesto;
use App\Modules\Ingreso\Domain\Exceptions\AnulacionFueraDeDiaException;
use App\Modules\Ingreso\Domain\Exceptions\AulaSinExamenException;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use App\Modules\Ingreso\Domain\Exceptions\IngresoNoPermitidoException;
use App\Modules\Ingreso\Domain\Exceptions\IngresoYaAnuladoException;
use App\Modules\Ingreso\Domain\Exceptions\YaExpulsadoException;
use App\Modules\Ingreso\Http\Requests\AnularIngresoRequest;
use App\Modules\Ingreso\Http\Requests\CarnetEnPuertaRequest;
use App\Modules\Ingreso\Http\Requests\ConsultarPuertaRequest;
use App\Modules\Ingreso\Http\Requests\FotoDeIntentoRequest;
use App\Modules\Ingreso\Http\Requests\RegistrarExcepcionRequest;
use App\Modules\Ingreso\Http\Requests\RegistrarExpulsionRequest;
use App\Modules\Ingreso\Http\Requests\VerificarRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El punto de control: trabaja por aula y hora, no por examen.
 */
final class PuertaController
{
    use Responde;

    public function aulas(Request $request, ListarAulasDelPuesto $listar): JsonResponse
    {
        return response()->json($listar->execute($this->operador($request)));
    }

    public function show(
        int $aula,
        ConsultarPuertaRequest $request,
        ConsultarPuerta $consultar,
        PuertaGateway $puerta,
    ): JsonResponse {
        $datos = $puerta->aula($aula);

        if ($datos === null) {
            return $this->noEncontrado('Aula no encontrada.');
        }

        try {
            return response()->json($consultar->execute(
                $datos,
                $this->operador($request),
                $request->todos(),
                $request->buscar(),
            ));
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        }
    }

    /**
     * Los edificios para el mapa de «Otra aula». Los alcanza tambien quien
     * entro con codigo de aula, que no puede leer `/api/edificios`.
     */
    public function edificios(
        int $aula,
        Request $request,
        ConsultarMapaDePuerta $consultar,
        PuertaGateway $puerta,
    ): JsonResponse {
        $datos = $puerta->aula($aula);

        if ($datos === null) {
            return $this->noEncontrado('Aula no encontrada.');
        }

        try {
            // Cambian solo al importar: el navegador puede guardarlos.
            return response()
                ->json($consultar->execute($datos, $this->operador($request)))
                ->header('Cache-Control', 'private, max-age=3600');
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        }
    }

    public function verificar(
        int $aula,
        VerificarRequest $request,
        VerificarEnPuerta $verificar,
        PuertaGateway $puerta,
        FotosDeEstudiantes $fotos,
    ): JsonResponse {
        $datos = $puerta->aula($aula);

        if ($datos === null) {
            return $this->noEncontrado('Aula no encontrada.');
        }

        try {
            $veredicto = $verificar->execute(
                $datos,
                $request->token(),
                $request->identificador(),
                $this->operador($request),
            );
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (AulaSinExamenException $error) {
            return $this->conflicto($error->getMessage(), 'AULA_SIN_EXAMEN');
        }

        if ($veredicto === null) {
            return $this->campoNoValido('identificador', 'Sin registro');
        }

        $respuesta = $veredicto->comoArreglo();

        // La foto va dentro del veredicto: quien controla compara la cara
        // sin otra peticion (y sin que la puerta con codigo de aula tenga
        // que alcanzar la ruta de fotos).
        if ($veredicto->estudiante !== null && is_array($respuesta['estudiante'])) {
            $jpeg = $fotos->foto($veredicto->estudiante->id);
            $respuesta['estudiante']['foto'] = $jpeg === null ? null : 'data:image/jpeg;base64,'.base64_encode($jpeg);
        }

        return response()->json($respuesta);
    }

    /**
     * La foto de quien intenta entrar sin corresponderle, como evidencia de
     * la incidencia que el sistema asento.
     */
    public function fotoDeIntento(
        int $aula,
        int $incidencia,
        FotoDeIntentoRequest $request,
        AgregarFotoDeIntento $agregar,
        PuertaGateway $puerta,
    ): JsonResponse {
        $datos = $puerta->aula($aula);

        if ($datos === null) {
            return $this->noEncontrado('Aula no encontrada.');
        }

        try {
            $fotos = $agregar->execute($datos, $this->operador($request), $incidencia, $request->contenido());
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        }

        return response()->json(['message' => 'Foto guardada.', 'evidencias' => $fotos], Response::HTTP_CREATED);
    }

    /**
     * Compara el carnet que presenta quien llega con el del estudiante.
     */
    public function carnet(
        int $aula,
        CarnetEnPuertaRequest $request,
        VerificarCarnetEnPuerta $verificar,
        PuertaGateway $puerta,
    ): JsonResponse {
        $datos = $puerta->aula($aula);

        if ($datos === null) {
            return $this->noEncontrado('Aula no encontrada.');
        }

        try {
            $resultado = $verificar->execute(
                $datos,
                $this->operador($request),
                $request->integer('estudiante_id'),
                $request->integer('examen_id'),
                $request->string('documento')->toString(),
            );
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        }

        return response()->json($resultado);
    }

    public function excepcion(
        int $aula,
        RegistrarExcepcionRequest $request,
        RegistrarExcepcion $registrar,
        PuertaGateway $puerta,
    ): JsonResponse {
        if ($this->operador($request)->esAcceso()) {
            return $this->soloConCuenta();
        }

        $datos = $puerta->aula($aula);

        if ($datos === null) {
            return $this->noEncontrado('Aula no encontrada.');
        }

        $tipo = $request->tipo();

        try {
            $resultado = $registrar->execute(
                $datos,
                $tipo,
                $request->motivo(),
                $request->estudianteId(),
                $request->examenId(),
                $this->operador($request),
            );
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (AulaSinExamenException $error) {
            return $this->conflicto($error->getMessage(), 'AULA_SIN_EXAMEN');
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        } catch (IngresoNoPermitidoException $error) {
            return $this->conflicto($error->getMessage(), $error->codigo);
        }

        return response()->json([
            'message' => 'Situación excepcional registrada: '.mb_strtolower($tipo->etiqueta()).'.',
            'incidencia_id' => $resultado['incidencia_id'],
            'ingreso' => $resultado['ingreso'],
        ], Response::HTTP_CREATED);
    }

    public function expulsion(
        int $ingreso,
        RegistrarExpulsionRequest $request,
        RegistrarExpulsion $registrar,
        IngresoGateway $ingresos,
    ): JsonResponse {
        if ($this->operador($request)->esAcceso()) {
            return $this->soloConCuenta();
        }

        $datos = $ingresos->porId($ingreso);

        if ($datos === null) {
            return $this->noEncontrado('Ingreso no encontrado.');
        }

        try {
            $incidenciaId = $registrar->execute($datos, $request->motivo(), $this->operador($request));
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (YaExpulsadoException $error) {
            return $this->conflicto($error->getMessage(), 'YA_EXPULSADO');
        }

        return response()->json([
            'message' => 'Expulsión registrada.',
            'incidencia_id' => $incidenciaId,
        ], Response::HTTP_CREATED);
    }

    /**
     * La anulación de un ingreso hecho por error: la hace el docente del
     * examen, el mismo día, con su motivo. El ingreso original queda.
     */
    public function anulacion(int $ingreso, AnularIngresoRequest $request, AnularIngreso $anular): JsonResponse
    {
        $operador = $this->operador($request);

        if ($operador->esAcceso() || $operador->usuarioId === null) {
            return $this->soloConCuenta();
        }

        try {
            if (! $anular->execute($ingreso, $request->motivo(), $operador->usuarioId)) {
                return $this->noEncontrado('Ingreso no encontrado.');
            }
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (IngresoYaAnuladoException $error) {
            return $this->conflicto($error->getMessage(), 'YA_ANULADO');
        } catch (AnulacionFueraDeDiaException $error) {
            return $this->conflicto($error->getMessage(), 'FUERA_DE_DIA');
        }

        return response()->json(['message' => 'Ingreso anulado.']);
    }

    /**
     * Con codigo de aula solo se verifica y se reportan incidencias: lo
     * que cambia la situacion de un estudiante lo hace una cuenta.
     */
    private function soloConCuenta(): JsonResponse
    {
        return response()->json([
            'message' => 'Con código de aula solo se verifica y se reportan incidencias.',
            'alcance' => true,
        ], 403);
    }
}
