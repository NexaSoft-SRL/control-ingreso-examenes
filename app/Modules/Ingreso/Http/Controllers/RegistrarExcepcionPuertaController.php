<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\RegistrarExcepcionEnPuerta;
use App\Modules\Ingreso\Domain\Enums\TipoExcepcion;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class RegistrarExcepcionPuertaController
{
    public function __invoke(
        Request $request,
        int $aula,
        RegistrarExcepcionEnPuerta $registrar,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        $request->validate([
            'tipo' => ['required', 'string', Rule::in(TipoExcepcion::valores())],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'estudiante_id' => ['nullable', 'integer', 'min:1', 'exists:estudiantes,id'],
            'examen_id' => ['nullable', 'integer', 'min:1'],
        ], [
            'tipo.required' => 'Obligatorio',
            'tipo.in' => 'Tipo no válido',
            'motivo.required' => 'Obligatorio',
            'motivo.min' => 'Mínimo 5 caracteres',
            'motivo.max' => 'Máximo 500 caracteres',
            'estudiante_id.exists' => 'Estudiante no encontrado',
        ]);

        $tipo = TipoExcepcion::from($request->string('tipo')->toString());
        $motivo = trim($request->string('motivo')->toString());

        if (mb_strlen($motivo) < 5) {
            return response()->json([
                'message' => 'Motivo no válido.',
                'errors' => ['motivo' => ['Mínimo 5 caracteres']],
            ], 422);
        }

        $estudianteId = $request->filled('estudiante_id') ? $request->integer('estudiante_id') : null;
        $examenId = $request->filled('examen_id') ? $request->integer('examen_id') : null;
        $fecha = (new DateTimeImmutable('now', new DateTimeZone('America/La_Paz')))->format('Y-m-d');

        try {
            $resultado = $registrar->ejecutar(
                $usuarioId,
                $aula,
                $fecha,
                $tipo,
                $motivo,
                $estudianteId,
                $examenId,
            );
        } catch (AccesoPuntoDenegado $error) {
            return response()->json(['message' => $error->getMessage()], 403);
        } catch (DomainException $error) {
            return response()->json(['message' => $error->getMessage()], 409);
        } catch (InvalidArgumentException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        $hora = $resultado['ingreso']['hora'] ?? null;

        return response()->json([
            'message' => $hora === null
                ? sprintf('Situación excepcional registrada: %s', mb_strtolower($tipo->etiqueta()))
                : 'Admitido como excepción',
            'data' => [
                ...$resultado,
                'tipo' => $tipo->value,
                'tipo_etiqueta' => $tipo->etiqueta(),
                'admitido' => $hora !== null,
            ],
        ], 201);
    }
}
