<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\ResponderInvitacionDeExamen;
use App\Modules\Examenes\Application\Actions\SalirDeExamen;
use App\Modules\Examenes\Application\DTOs\InvitacionRecibidaData;
use App\Modules\Examenes\Application\Queries\ListarInvitacionesDeExamen;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenConIngresosException;
use App\Modules\Examenes\Domain\Exceptions\InvitacionAjenaException;
use App\Modules\Examenes\Domain\Exceptions\InvitacionYaRespondidaException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Colaboracion entre docentes: las invitaciones a sumar un grupo al examen
 * de otro, su respuesta y la salida de un examen al que ya se sumo.
 */
final class InvitacionExamenController
{
    use RespuestasDeExamen;

    public function index(ListarInvitacionesDeExamen $listar): JsonResponse
    {
        $invitaciones = $listar->execute($this->usuarioId());

        return response()->json([
            'data' => array_map(
                static fn (InvitacionRecibidaData $invitacion): array => [
                    'id' => $invitacion->id,
                    'examen' => [
                        'id' => $invitacion->examenId,
                        'tipo' => $invitacion->tipo,
                        'tipo_texto' => $invitacion->tipoTexto,
                        'asignatura' => [
                            'id' => $invitacion->asignaturaId,
                            'codigo' => $invitacion->asignaturaCodigo,
                            'nombre' => $invitacion->asignaturaNombre,
                        ],
                        'fecha' => $invitacion->fecha,
                        'hora' => $invitacion->hora,
                        'duracion' => $invitacion->duracion,
                        'aulas' => $invitacion->aulas,
                    ],
                    'grupo' => [
                        'id' => $invitacion->grupoId,
                        'codigo' => $invitacion->grupoCodigo,
                    ],
                    'invitado_por' => $invitacion->invitadoPor,
                ],
                $invitaciones,
            ),
            'meta' => ['total' => count($invitaciones)],
        ]);
    }

    public function aceptar(int $invitacion, ResponderInvitacionDeExamen $responder): JsonResponse
    {
        try {
            $existe = $responder->aceptar($invitacion, $this->usuarioId());
        } catch (InvitacionAjenaException) {
            return $this->invitacionAjena();
        } catch (InvitacionYaRespondidaException) {
            return $this->yaRespondida();
        } catch (ExamenConIngresosException) {
            return $this->conIngresos('El examen ya tiene ingresos registrados: ya no se puede sumar un grupo.');
        } catch (DatoInvalidoException $error) {
            return $this->invalido($error);
        }

        return $existe
            ? response()->json(['message' => 'Invitación aceptada.'])
            : $this->noEncontrado('Invitación no encontrada.');
    }

    public function rechazar(int $invitacion, ResponderInvitacionDeExamen $responder): JsonResponse
    {
        try {
            $existe = $responder->rechazar($invitacion, $this->usuarioId());
        } catch (InvitacionAjenaException) {
            return $this->invitacionAjena();
        } catch (InvitacionYaRespondidaException) {
            return $this->yaRespondida();
        }

        return $existe
            ? response()->json(['message' => 'Invitación rechazada.'])
            : $this->noEncontrado('Invitación no encontrada.');
    }

    public function salir(int $examen, SalirDeExamen $salir): JsonResponse
    {
        try {
            $salio = $salir->execute($examen, $this->usuarioId());
        } catch (ExamenAjenoException) {
            return response()->json([
                'message' => 'No participa en este examen como colaborador.',
                'alcance' => true,
            ], Response::HTTP_FORBIDDEN);
        } catch (ExamenConIngresosException) {
            return $this->conIngresos('El examen ya tiene ingresos registrados: ya no se puede salir.');
        }

        return $salio
            ? response()->json(['message' => 'Salió del examen.'])
            : $this->noEncontrado();
    }

    private function invitacionAjena(): JsonResponse
    {
        return response()->json([
            'message' => 'Esta invitación no es de un grupo suyo.',
            'alcance' => true,
        ], Response::HTTP_FORBIDDEN);
    }

    private function yaRespondida(): JsonResponse
    {
        return response()->json([
            'message' => 'Esta invitación ya se respondió.',
            'codigo' => 'INVITACION_RESPONDIDA',
        ], Response::HTTP_CONFLICT);
    }
}
