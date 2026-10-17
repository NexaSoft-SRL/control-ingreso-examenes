<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Ingreso\Application\Actions\AtenderIncidenciaAula;
use App\Modules\Ingreso\Application\Contracts\SeguimientoAulaGateway;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class SeguimientoAulaController
{
    public function aulas(
        SeguimientoAulaGateway $seguimiento,
    ): JsonResponse {
        $usuarioId = $this->usuarioId();

        return response()->json([
            'data' => $seguimiento->aulasDelUsuario($usuarioId),
        ]);
    }

    public function detalle(
        Request $request,
        int $examen,
        int $aula,
        SeguimientoAulaGateway $seguimiento,
    ): JsonResponse {
        $request->validate([
            'estado' => ['sometimes', 'string', 'in:faltan,ingresaron'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
        ]);
        $estado = $request->string('estado', 'faltan')->toString();
        $valorBuscar = $request->input('buscar');
        $buscar = is_string($valorBuscar) ? $valorBuscar : '';
        $pagina = $request->integer('pagina', 1);
        $usuarioId = $this->usuarioId();

        if (! $seguimiento->aulaPerteneceAExamen($examen, $aula)) {
            return $this->error('Aula no encontrada.', 404);
        }

        if (! $seguimiento->puedeVerAula($usuarioId, $examen, $aula)) {
            $asignada = $seguimiento->tieneAsignacion($usuarioId, $examen, $aula);
            $fueraDeFecha = $seguimiento->fechaDeExamen($examen) !== now()->toDateString();
            $mensaje = $asignada && $fueraDeFecha
                ? 'Solo puedes seguir tu aula el día del examen.'
                : 'Esta aula no es tuya.';

            return $this->error($mensaje, 403);
        }

        try {
            return response()->json([
                'data' => $seguimiento->detalle(
                    $examen,
                    $aula,
                    $estado,
                    $buscar,
                    $pagina,
                ),
            ]);
        } catch (InvalidArgumentException $error) {
            return $this->error($error->getMessage(), 404);
        }
    }

    public function auxiliares(
        int $examen,
        SeguimientoAulaGateway $seguimiento,
        AlcanceExamenGateway $alcanceExamen,
    ): JsonResponse {
        if (! $alcanceExamen->esDocenteDelExamen($this->usuarioId(), $examen)) {
            return $this->error('Solo el docente del examen puede asignar auxiliares.', 403);
        }

        return response()->json(['data' => $seguimiento->auxiliares()]);
    }

    public function asignaciones(
        int $examen,
        SeguimientoAulaGateway $seguimiento,
        AlcanceExamenGateway $alcanceExamen,
    ): JsonResponse {
        if (! $alcanceExamen->esDocenteDelExamen($this->usuarioId(), $examen)) {
            return $this->error('Solo el docente del examen puede asignar auxiliares.', 403);
        }

        return response()->json([
            'data' => $seguimiento->asignaciones($examen),
            'aulas' => $seguimiento->aulasDelExamen($examen),
            'auxiliares' => $seguimiento->auxiliares(),
        ]);
    }

    public function asignar(
        Request $request,
        int $examen,
        SeguimientoAulaGateway $seguimiento,
        AlcanceExamenGateway $alcanceExamen,
    ): JsonResponse {
        $usuarioId = $this->usuarioId();

        if (! $alcanceExamen->esDocenteDelExamen($usuarioId, $examen)) {
            return $this->error('Solo el docente del examen puede asignar auxiliares.', 403);
        }

        $request->validate([
            'usuario_id' => ['required', 'integer', 'min:1'],
            'aula_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $asignacionId = $seguimiento->asignarAuxiliar(
                $request->integer('usuario_id'),
                $examen,
                $request->integer('aula_id'),
                $usuarioId,
            );
        } catch (InvalidArgumentException $error) {
            return $this->error($error->getMessage(), 422);
        }

        return response()->json([
            'data' => ['id' => $asignacionId],
            'message' => 'Auxiliar asignado al aula.',
        ], 201);
    }

    public function quitarAsignacion(
        int $examen,
        int $asignacion,
        SeguimientoAulaGateway $seguimiento,
        AlcanceExamenGateway $alcanceExamen,
    ): JsonResponse {
        if (! $alcanceExamen->esDocenteDelExamen($this->usuarioId(), $examen)) {
            return $this->error('Solo el docente del examen puede cambiar asignaciones.', 403);
        }

        if (! $seguimiento->quitarAsignacion($asignacion, $examen)) {
            return $this->error('Asignación no encontrada.', 404);
        }

        return response()->json(['message' => 'Asignación retirada.']);
    }

    public function atenderIncidencia(
        int $incidencia,
        AtenderIncidenciaAula $atender,
    ): JsonResponse {
        try {
            $actualizada = $atender->ejecutar($this->usuarioId(), $incidencia);
        } catch (AccesoPuntoDenegado $error) {
            return $this->error($error->getMessage(), 403);
        } catch (InvalidArgumentException $error) {
            return $this->error($error->getMessage(), 404);
        }

        if (! $actualizada) {
            return $this->error('La incidencia ya fue atendida.', 409);
        }

        return response()->json(['message' => 'Incidencia atendida.']);
    }

    private function usuarioId(): int
    {
        $id = Auth::id();

        if (! is_int($id)) {
            throw new InvalidArgumentException('No hay una sesión activa.');
        }

        return $id;
    }

    private function error(string $mensaje, int $estado): JsonResponse
    {
        return response()->json(['message' => $mensaje], $estado);
    }
}
