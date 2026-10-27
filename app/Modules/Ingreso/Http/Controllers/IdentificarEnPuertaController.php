<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\RegistrarIntentoEnPuerta;
use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use App\Modules\Ingreso\Application\Queries\DetallarVeredictoPuerta;
use App\Modules\Ingreso\Application\Queries\IdentificarEnPuerta;
use App\Modules\Ingreso\Application\Queries\ResolverIdentificador;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Http\Middleware\ExigirPuesto;
use App\Modules\Ingreso\Http\Requests\IdentificarEnPuertaRequest;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class IdentificarEnPuertaController
{
    public function __invoke(
        IdentificarEnPuertaRequest $request,
        int $aula,
        ResolverIdentificador $identificadores,
        IdentificarEnPuerta $identificar,
        RegistrarIntentoEnPuerta $intentos,
        DetallarVeredictoPuerta $detallar,
        ExamenesDePuerta $examenesDePuerta,
    ): JsonResponse {
        $operador = ExigirPuesto::operador($request);

        $resuelto = $identificadores->ejecutar($request->tipo(), $request->identificador());
        $datos = ['tipo' => $resuelto['tipo'], 'identificador' => $resuelto['valor']];
        $medio = $resuelto['medio'];

        $ahora = new DateTimeImmutable(
            'now',
            new DateTimeZone('America/La_Paz'),
        );
        $fecha = $ahora->format('Y-m-d');

        try {
            $resultado = $identificar->ejecutar(
                $operador,
                $aula,
                $datos['tipo'],
                $datos['identificador'],
                $ahora,
            );

            // BUG-04: quien no puede entrar deja su intento registrado,
            // tambien cuando solo se lo identifica.
            $intentos->ejecutar(
                $operador,
                $aula,
                $medio,
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
            $medio,
            $datos['identificador'],
            $resultado->causa === null ? [] : $examenesDePuerta->examenesDelAula($aula, $fecha),
        );

        return response()->json([
            'data' => [
                ...$detalle,
                'examen_id' => $resultado->examenId,
                'veredicto' => $resultado->veredicto?->value,
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
