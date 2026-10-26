<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\RegistrarIngreso;
use App\Modules\Ingreso\Application\Actions\RegistrarIntentoEnPuerta;
use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use App\Modules\Ingreso\Application\Contracts\OperacionesPuertaGateway;
use App\Modules\Ingreso\Application\Queries\DetallarVeredictoPuerta;
use App\Modules\Ingreso\Application\Queries\IdentificarEnPuerta;
use App\Modules\Ingreso\Application\Queries\ResolverIdentificador;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Http\Requests\IdentificarEnPuertaRequest;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class RegistrarIngresoEnPuertaController
{
    public function __invoke(
        IdentificarEnPuertaRequest $request,
        int $aula,
        ResolverIdentificador $identificadores,
        IdentificarEnPuerta $identificar,
        RegistrarIngreso $registrar,
        OperacionesPuertaGateway $operaciones,
        RegistrarIntentoEnPuerta $intentos,
        DetallarVeredictoPuerta $detallar,
        ExamenesDePuerta $examenesDePuerta,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        $resuelto = $identificadores->ejecutar($request->tipo(), $request->identificador());
        $tipo = $resuelto['tipo'];
        $identificador = $resuelto['valor'];
        $medio = $resuelto['medio'];

        $ahora = new DateTimeImmutable(
            'now',
            new DateTimeZone('America/La_Paz'),
        );
        $fecha = $ahora->format('Y-m-d');

        try {
            // El examen se obtiene del servidor, nunca del cliente.
            $resultado = $identificar->ejecutar(
                $usuarioId,
                $aula,
                $tipo,
                $identificador,
                $ahora,
            );

            if (
                ! $resultado->puedeAdmitir()
                && ($resultado->ambiguo || $resultado->examenId === null)
            ) {
                // BUG-04: el intento queda registrado y distinguido (no
                // inscrito, sin registro, QR no valido, ambiguo) sin
                // adjudicarlo a uno de los examenes del aula.
                $intentos->ejecutar(
                    $usuarioId,
                    $aula,
                    $medio,
                    $identificador,
                    $resultado,
                );

                return response()->json([
                    'message' => $resultado->ambiguo
                        ? 'Identificación ambigua entre exámenes.'
                        : 'Ingreso no autorizado para este estudiante.',
                    'data' => $detallar->detallar(
                        $resultado,
                        null,
                        $aula,
                        $fecha,
                        $medio,
                        $identificador,
                        $examenesDePuerta->examenesDelAula($aula, $fecha),
                    ),
                ], Response::HTTP_CONFLICT);
            }

            $examenId = $resultado->examenId;

            if ($examenId === null) {
                throw new DomainException(
                    'No se pudo resolver el examen.'
                );
            }

            // El código fue descifrado y asociado a un examen autorizado.
            $tipoRegistro = $tipo === 'qr' ? 'codigo' : $tipo;
            $identificadorRegistro = $tipo === 'qr'
                ? $resultado->codigoQrResuelto
                : $identificador;

            if ($identificadorRegistro === null) {
                throw new DomainException(
                    'No se pudo validar el código QR.'
                );
            }

            // Esta acción vuelve a validar las reglas antes de persistir.
            $registro = $registrar->ejecutarDetallado(
                $usuarioId,
                $examenId,
                $aula,
                $tipoRegistro,
                $identificadorRegistro,
                porQr: $tipo === 'qr',
            );
            $veredicto = $registro->veredicto;
        } catch (AccesoPuntoDenegado $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        } catch (DomainException $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], Response::HTTP_CONFLICT);
        } catch (InvalidArgumentException $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $estado = match ($veredicto) {
            VeredictoIngreso::Admitido => Response::HTTP_CREATED,
            VeredictoIngreso::YaIngreso => Response::HTTP_CONFLICT,
            default => Response::HTTP_CONFLICT,
        };

        $fechaIngreso = in_array($veredicto, [VeredictoIngreso::Admitido, VeredictoIngreso::YaIngreso], true)
            ? $operaciones->fechaIngreso(
                $examenId,
                $resultado->candidato?->estudiante->id,
            )
            : null;

        return response()->json([
            'data' => [
                ...$detallar->detallar(
                    $resultado,
                    $veredicto,
                    $aula,
                    $fecha,
                    $medio,
                    $identificador,
                    incidenciaRegistrada: $registro->incidenciaRegistrada,
                ),
                'examen_id' => $examenId,
                'veredicto' => $veredicto->value,
                'admitido' => $veredicto->permiteIngreso(),
                'registrado_en' => $fechaIngreso,
            ],
        ], $estado);
    }
}
