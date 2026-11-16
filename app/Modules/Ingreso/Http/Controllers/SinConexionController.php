<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\ConsultarSinConexionDelExamen;
use App\Modules\Ingreso\Application\Actions\ObtenerInstantaneaSinConexion;
use App\Modules\Ingreso\Application\Actions\ResolverIrregularidad;
use App\Modules\Ingreso\Application\Actions\SincronizarSinConexion;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Domain\Exceptions\AulaSinExamenException;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use App\Modules\Ingreso\Http\Requests\ResolverIrregularidadRequest;
use App\Modules\Ingreso\Http\Requests\SincronizarSinConexionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El modo sin conexion de la puerta: la instantanea que el puesto descarga,
 * lo que envia al volver la conexion y la revision del docente.
 */
final class SinConexionController
{
    use Responde;

    public function instantanea(int $aula, Request $request, ObtenerInstantaneaSinConexion $obtener, PuertaGateway $puerta): JsonResponse
    {
        $dispositivo = $request->query('dispositivo');

        if (! is_string($dispositivo) || mb_strlen($dispositivo) < 8 || mb_strlen($dispositivo) > 64) {
            return $this->campoNoValido('dispositivo', 'Falta el identificador del puesto.');
        }

        $datos = $puerta->aula($aula);

        if ($datos === null) {
            return $this->noEncontrado('Aula no encontrada.');
        }

        try {
            return response()->json($obtener->execute($datos, $this->operador($request), $dispositivo));
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (AulaSinExamenException $error) {
            return $this->conflicto($error->getMessage(), 'AULA_SIN_EXAMEN');
        }
    }

    public function sincronizar(int $aula, SincronizarSinConexionRequest $request, SincronizarSinConexion $sincronizar, PuertaGateway $puerta): JsonResponse
    {
        $datos = $puerta->aula($aula);

        if ($datos === null) {
            return $this->noEncontrado('Aula no encontrada.');
        }

        try {
            $resumen = $sincronizar->execute(
                $datos,
                $this->operador($request),
                $request->string('dispositivo')->toString(),
                $request->string('instantanea_en')->toString(),
                $request->eventos(),
            );
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        }

        return response()->json($resumen);
    }

    public function revision(int $examen, ConsultarSinConexionDelExamen $consultar, PuertaGateway $puerta): JsonResponse
    {
        $usuarioId = $this->usuarioId();

        if ($usuarioId === null) {
            return response()->json(['message' => 'No hay una sesión activa.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($puerta->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            return response()->json($consultar->execute($examen, $usuarioId));
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        }
    }

    public function resolver(int $irregularidad, ResolverIrregularidadRequest $request, ResolverIrregularidad $resolver): JsonResponse
    {
        $usuarioId = $this->usuarioId();

        if ($usuarioId === null) {
            return response()->json(['message' => 'No hay una sesión activa.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $existe = $resolver->execute(
                $irregularidad,
                $request->string('estado')->toString(),
                $request->string('nota')->toString(),
                $usuarioId,
            );
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        }

        if (! $existe) {
            return $this->noEncontrado('Irregularidad no encontrada.');
        }

        return response()->json(['message' => 'Irregularidad resuelta.']);
    }
}
