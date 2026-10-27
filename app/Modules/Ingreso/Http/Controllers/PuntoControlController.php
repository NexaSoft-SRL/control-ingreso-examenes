<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\RegistrarIngreso;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IngresosRecientesGateway;
use App\Modules\Ingreso\Application\Queries\ConsultarCandidato;
use App\Modules\Ingreso\Application\Queries\ConsultarIngresosRecientes;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class PuntoControlController
{
    public function candidato(
        Request $request,
        int $examen,
        int $aula,
        AlcancePuntoControl $alcance,
        ConsultarCandidato $consulta,
        CodigoQrGateway $qr,
        IngresoGateway $ingresoGateway,
    ): JsonResponse {
        $acceso = $request->attributes->get('acceso_codigo_aula');
        $usuario = Auth::id();

        if ($acceso === null && ! is_int($usuario)) {
            return $this->error('No hay una sesión activa.', 401);
        }

        if ($acceso === null && ! $alcance->puedeOperar((int) $usuario, $examen, $aula)) {
            return $this->error('Punto de control no autorizado.', 403);
        }

        $request->validate([
            'tipo' => ['required', 'string', Rule::in(['codigo', 'documento', 'qr'])],
            'identificador' => ['required', 'string', 'max:2048'],
        ]);

        try {
            $tipo = $request->string('tipo')->toString();
            $identificador = $request->string('identificador')->toString();

            if ($tipo === 'qr') {
                $identificador = $qr->resolver($identificador, $examen);
                $tipo = 'codigo';
            }

            $candidato = $consulta->consultar(
                $examen,
                $tipo,
                $identificador,
            );
        } catch (InvalidArgumentException $error) {
            return $this->error($error->getMessage(), 422);
        }

        if ($candidato === null) {
            return $this->error('Estudiante no encontrado.', 404);
        }

        $admisible = false;
        $veredicto = VeredictoIngreso::NoCorresponde->value;

        if (! $candidato->inscrito) {
            $veredicto = VeredictoIngreso::NoCorresponde->value;
        } elseif ($candidato->habilitacion === null || ! $candidato->habilitacion->habilitado) {
            $veredicto = VeredictoIngreso::NoHabilitado->value;
        } else {
            $yaIngreso = $ingresoGateway->yaIngreso($examen, $candidato->estudiante->id);

            if ($yaIngreso) {
                $veredicto = VeredictoIngreso::YaIngreso->value;
            } else {
                $veredicto = VeredictoIngreso::Admitido->value;
                $admisible = true;
            }
        }

        return response()->json([
            'data' => [
                'estudiante_id' => $candidato->estudiante->id,
                'codigo' => $candidato->estudiante->codigoUniversitario,
                'nombre' => $candidato->estudiante->nombreCompleto(),
                'inscrito' => $candidato->inscrito,
                'habilitado' => $candidato->habilitacion?->habilitado,
                'motivo_inhabilitacion' => $candidato->habilitacion?->habilitado === false
                    ? $candidato->habilitacion->motivo
                    : null,
                'aula_asignada_id' => $candidato->habilitacion?->aulaId,
                'examen_id' => $examen,
                'admisible' => $admisible,
                'veredicto' => $veredicto,
            ],
        ]);
    }

    public function registrar(
        Request $request,
        int $examen,
        int $aula,
        AlcancePuntoControl $alcance,
        RegistrarIngreso $registrar,
        CodigoQrGateway $qr,
    ): JsonResponse {
        $acceso = $request->attributes->get('acceso_codigo_aula');
        $usuario = Auth::id();

        if ($acceso === null && ! is_int($usuario)) {
            return $this->error('No hay una sesión activa.', 401);
        }

        if ($acceso === null && ! $alcance->puedeOperar((int) $usuario, $examen, $aula)) {
            return $this->error('Punto de control no autorizado.', 403);
        }

        $request->validate([
            'tipo' => ['required', 'string', Rule::in(['codigo', 'documento', 'qr'])],
            'identificador' => ['required', 'string', 'max:2048'],
        ]);

        try {
            $tipo = $request->string('tipo')->toString();
            $identificador = $request->string('identificador')->toString();

            if ($tipo === 'qr') {
                $identificador = $qr->resolver($identificador, $examen);
                $tipo = 'codigo';
            }

            $accesoId = is_object($acceso) && property_exists($acceso, 'id') && is_numeric($acceso->id)
                ? (int) $acceso->id
                : null;

            $veredicto = $registrar->ejecutar(
                is_int($usuario) ? $usuario : null,
                $examen,
                $aula,
                $tipo,
                $identificador,
                $accesoId,
            );
        } catch (AccesoPuntoDenegado $error) {
            return $this->error($error->getMessage(), 403);
        } catch (DomainException $error) {
            return $this->error($error->getMessage(), 409);
        } catch (InvalidArgumentException $error) {
            return $this->error($error->getMessage(), 422);
        }

        $codigo = match ($veredicto) {
            VeredictoIngreso::Admitido => Response::HTTP_CREATED,
            VeredictoIngreso::YaIngreso => Response::HTTP_CONFLICT,
            default => Response::HTTP_OK,
        };

        return response()->json([
            'data' => [
                'veredicto' => $veredicto->value,
                'admitido' => $veredicto->permiteIngreso(),
            ],
        ], $codigo);
    }

    public function recientes(
        Request $request,
        int $examen,
        int $aula,
        AlcancePuntoControl $alcance,
        ConsultarIngresosRecientes $consulta,
        IngresosRecientesGateway $ingresosGateway,
    ): JsonResponse {
        $acceso = $request->attributes->get('acceso_codigo_aula');
        $usuario = Auth::id();

        if ($acceso === null && ! is_int($usuario)) {
            return $this->error('No hay una sesión activa.', 401);
        }

        if ($acceso === null && ! $alcance->puedeOperar((int) $usuario, $examen, $aula)) {
            return $this->error('Punto de control no autorizado.', 403);
        }

        $request->validate([
            'limite' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $limite = $request->integer('limite', 20);

        try {
            if ($acceso !== null) {
                if ($limite < 1 || $limite > 50) {
                    throw new InvalidArgumentException('Parámetros de consulta inválidos.');
                }
                $filas = $ingresosGateway->recientes($examen, $aula, $limite);
            } else {
                $filas = $consulta->ejecutar(
                    (int) $usuario,
                    $examen,
                    $aula,
                    $limite,
                );
            }
        } catch (AccesoPuntoDenegado $error) {
            return $this->error($error->getMessage(), 403);
        } catch (InvalidArgumentException $error) {
            return $this->error($error->getMessage(), 422);
        }

        return response()->json([
            'data' => $filas,
            'meta' => ['cantidad' => count($filas)],
        ]);
    }

    private function error(string $mensaje, int $codigo): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
        ], $codigo);
    }
}
