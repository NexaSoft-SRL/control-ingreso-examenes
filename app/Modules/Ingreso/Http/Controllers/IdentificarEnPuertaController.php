<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\RegistrarIntentoEnPuerta;
use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use App\Modules\Ingreso\Application\Queries\DetallarVeredictoPuerta;
use App\Modules\Ingreso\Application\Queries\IdentificarEnPuerta;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class IdentificarEnPuertaController
{
    /**
     * Mensajes de la identificacion en la puerta (BUG-16).
     *
     * @var array<string, string>
     */
    public const MENSAJES = [
        'tipo.required' => 'Obligatorio',
        'tipo.in' => 'Tipo de identificación no válido',
        'identificador.required' => 'Obligatorio',
        'identificador.string' => 'Identificación no válida',
        'identificador.max' => 'Código QR no válido',
    ];

    public function __invoke(
        Request $request,
        int $aula,
        IdentificarEnPuerta $identificar,
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

        /** @var array{tipo: string, identificador: string} $datos */
        $datos = $request->validate([
            'tipo' => ['required', 'string', Rule::in(['codigo', 'documento', 'qr'])],
            'identificador' => [
                'required',
                'string',
                'max:2048',
            ],
        ], self::MENSAJES);

        if ($datos['tipo'] !== 'qr') {
            $request->validate([
                'identificador' => [
                    'required',
                    'string',
                    'regex:/\A[0-9]{5,10}\z/D',
                ],
            ], [
                'identificador.regex' => 'Debe contener entre 5 y 10 dígitos.',
            ]);
        }

        $ahora = new DateTimeImmutable(
            'now',
            new DateTimeZone('America/La_Paz'),
        );
        $fecha = $ahora->format('Y-m-d');

        try {
            $resultado = $identificar->ejecutar(
                $usuarioId,
                $aula,
                $datos['tipo'],
                $datos['identificador'],
                $ahora,
            );

            // BUG-04: quien no puede entrar deja su intento registrado,
            // tambien cuando solo se lo identifica.
            $intentos->ejecutar(
                $usuarioId,
                $aula,
                $datos['tipo'],
                $datos['identificador'],
                $resultado,
            );
        } catch (AccesoPuntoDenegado $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 422);
        }

        $detalle = $detallar->detallar(
            $resultado,
            $resultado->veredicto,
            $aula,
            $fecha,
            $datos['tipo'],
            $datos['identificador'],
            $resultado->causa === null ? [] : $examenesDePuerta->examenesDelAula($aula, $fecha),
        );

        return response()->json([
            'data' => [
                ...$detalle,
                'examen_id' => $resultado->examenId,
                'veredicto' => $resultado->veredicto?->value,
                'ambiguo' => $resultado->ambiguo,
                'admisible' => $resultado->puedeAdmitir(),
                'codigo' => $detalle['codigo'],
                'nombre' => $detalle['nombre'],
                'inscrito' => $resultado->candidato?->inscrito,
                'habilitado' => $resultado->candidato?->habilitacion?->habilitado,
                'motivo_inhabilitacion' => $resultado->candidato?->habilitacion?->habilitado === false
                    ? $resultado->candidato->habilitacion->motivo : null,
                'revisado_por' => $resultado->candidato?->habilitacion?->registradaPor,
                'sin_revisar' => $resultado->candidato !== null
                    && $resultado->candidato->inscrito
                    && $resultado->candidato->habilitacion === null,
                'aula_asignada_id' => $resultado->candidato?->habilitacion?->aulaId,
                'aula_asignada_nombre' => $resultado->candidato?->habilitacion?->aulaNombre,
            ],
        ]);
    }
}
