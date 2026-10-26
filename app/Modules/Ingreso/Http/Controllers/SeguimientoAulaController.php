<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\AtenderIncidenciaAula;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\SeguimientoAulaGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
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
            'data' => $seguimiento->aulasDeHoy($usuarioId),
        ]);
    }

    public function detalle(
        Request $request,
        int $examen,
        int $aula,
        SeguimientoAulaGateway $seguimiento,
        AlcancePuntoControl $alcance,
        VentanaPuntoControl $ventana,
    ): JsonResponse {
        $request->validate([
            'estado' => ['sometimes', 'string', 'in:faltan,ingresaron'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
        ], [
            'estado.string' => 'El estado no es válido.',
            'estado.in' => 'El estado no es válido.',
            'buscar.string' => 'La búsqueda debe ser un texto.',
            'buscar.max' => 'La búsqueda admite hasta 100 caracteres.',
            'pagina.integer' => 'La página debe ser un número entero.',
            'pagina.min' => 'La página debe ser al menos 1.',
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
            $mensaje = $seguimiento->tieneAlcance($usuarioId, $examen, $aula)
                ? 'Disponible solo el día del examen.'
                : 'Aula fuera de su asignación.';

            return $this->error($mensaje, 403);
        }

        try {
            $detalle = $seguimiento->detalle(
                $examen,
                $aula,
                $estado,
                $buscar,
                $pagina,
            );

            // «Incidencia» y «Controlar» usan la puerta de esta aula (HU-19):
            // solo quien puede operarla, y «Controlar» con el ingreso abierto.
            $detalle['puede_operar'] = $alcance->puedeOperar($usuarioId, $examen, $aula);
            $detalle['abierto'] = $ventana->estaAbierta($examen);
            // Atiende el docente del examen o el personal del aula (como «En curso»).
            $detalle['puede_atender'] = $seguimiento->tieneAlcance($usuarioId, $examen, $aula);

            return response()->json(['data' => $detalle]);
        } catch (InvalidArgumentException $error) {
            return $this->error($error->getMessage(), 404);
        }
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
            return $this->error('La incidencia ya estaba atendida.', 409);
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
