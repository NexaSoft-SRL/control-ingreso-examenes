<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\RegistrarIngreso;
use App\Modules\Ingreso\Application\Queries\IdentificarEnPuerta;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class RegistrarIngresoEnPuertaController
{
    public function __invoke(
        Request $request,
        int $aula,
        IdentificarEnPuerta $identificar,
        RegistrarIngreso $registrar,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        $request->validate([
            'tipo' => [
                'required',
                'string',
                Rule::in(['codigo', 'documento']),
            ],
            'identificador' => [
                'required',
                'string',
                'regex:/^[0-9]{5,10}$/D',
            ],
        ]);

        $tipo = $request->string('tipo')->toString();
        $identificador = $request->string('identificador')->toString();

        try {
            // El examen se obtiene del servidor, nunca del cliente.
            $resultado = $identificar->ejecutar(
                $usuarioId,
                $aula,
                $tipo,
                $identificador,
                new DateTimeImmutable(
                    'now',
                    new DateTimeZone('America/La_Paz'),
                ),
            );

            if (! $resultado->puedeAdmitir()) {
                return response()->json([
                    'message' => $resultado->ambiguo
                        ? 'Identificación ambigua entre exámenes.'
                        : 'Ingreso no autorizado para este estudiante.',
                    'data' => [
                        'veredicto' => $resultado->veredicto?->value,
                        'ambiguo' => $resultado->ambiguo,
                        'admitido' => false,
                    ],
                ], Response::HTTP_CONFLICT);
            }

            $examenId = $resultado->examenId;

            if ($examenId === null) {
                throw new DomainException(
                    'No se pudo resolver el examen.'
                );
            }

            // Esta acción vuelve a validar las reglas antes de persistir.
            $veredicto = $registrar->ejecutar(
                $usuarioId,
                $examenId,
                $aula,
                $tipo,
                $identificador,
            );
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
            default => Response::HTTP_OK,
        };

        return response()->json([
            'data' => [
                'examen_id' => $examenId,
                'veredicto' => $veredicto->value,
                'admitido' => $veredicto->permiteIngreso(),
            ],
        ], $estado);
    }
}
