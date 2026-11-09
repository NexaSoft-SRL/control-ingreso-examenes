<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Contracts\IncidenciasComunesGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Http\Requests\GuardarIncidenciaComunRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * La lista de incidencias comunes: la que se ofrece al registrar en un
 * examen (la del docente) y la que cada cuenta arma con sus gravedades.
 */
final class IncidenciasComunesController
{
    use Responde;

    /**
     * Lo que se ofrece al registrar en ese examen. `puede_guardar`: si quien
     * registra tiene cuenta y puede guardar una nueva en su lista.
     */
    public function catalogo(int $examen, Request $request, IncidenciasComunesGateway $comunes, PuertaGateway $puerta): JsonResponse
    {
        if ($puerta->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        $operador = $this->operador($request);
        $dueno = $comunes->duenoParaExamen($examen, $operador->usuarioId);

        return response()->json([
            'data' => $dueno === null ? [] : $comunes->deUsuario($dueno),
            'puede_guardar' => $operador->usuarioId !== null,
        ]);
    }

    public function index(IncidenciasComunesGateway $comunes): JsonResponse
    {
        $usuarioId = $this->cuenta();

        return response()->json(['data' => $usuarioId === null ? [] : $comunes->deUsuario($usuarioId)]);
    }

    public function guardar(GuardarIncidenciaComunRequest $request, IncidenciasComunesGateway $comunes): JsonResponse
    {
        $usuarioId = $this->cuenta();

        if ($usuarioId === null) {
            return response()->json(['message' => 'No hay una sesión activa.'], Response::HTTP_UNAUTHORIZED);
        }

        $tipo = $request->tipo();
        $nombre = $request->nombre();

        if ($tipo !== null) {
            $comunes->guardarTipo($usuarioId, $tipo, $request->gravedad());
        } elseif ($nombre !== null) {
            $comunes->guardarPropia($usuarioId, $nombre, $request->gravedad());
        }

        return response()->json(['data' => $comunes->deUsuario($usuarioId), 'message' => 'Guardada.']);
    }

    public function quitar(int $id, IncidenciasComunesGateway $comunes): JsonResponse
    {
        $usuarioId = $this->cuenta();

        if ($usuarioId === null || ! $comunes->quitarPropia($usuarioId, $id)) {
            return $this->noEncontrado('Incidencia no encontrada.');
        }

        return response()->json(['data' => $comunes->deUsuario($usuarioId), 'message' => 'Quitada.']);
    }

    private function cuenta(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }
}
