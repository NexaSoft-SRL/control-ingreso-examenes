<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\MoverEnElExamen;
use App\Modules\Ingreso\Application\Actions\ResolverAccesoDeAula;
use App\Modules\Ingreso\Application\Queries\ConsultarControlDelExamen;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use App\Modules\Ingreso\Http\Requests\MoverEnElExamenRequest;
use App\Modules\Ingreso\Http\Requests\ResolverAccesoRequest;
use Illuminate\Http\JsonResponse;

/**
 * El panel en vivo del docente: quien controla cada aula, los accesos con
 * codigo que esperan su autorizacion y los movimientos de personas y de
 * estudiantes entre aulas del examen.
 */
final class ControlDelExamenController
{
    use Responde;

    private const AVISOS = [
        'AUTORIZADO' => 'Acceso autorizado.',
        'RECHAZADO' => 'Acceso rechazado.',
        'CORTADO' => 'Acceso cortado.',
    ];

    public function show(int $examen, ConsultarControlDelExamen $consultar): JsonResponse
    {
        try {
            return response()->json($consultar->execute($examen, $this->usuarioId() ?? 0));
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        }
    }

    public function resolver(
        int $examen,
        int $acceso,
        string $accion,
        ResolverAccesoRequest $request,
        ResolverAccesoDeAula $resolver,
    ): JsonResponse {
        $aula = $request->validated('aula_id');

        try {
            $estado = $resolver->execute(
                $examen,
                $acceso,
                $accion,
                $this->usuarioId() ?? 0,
                is_numeric($aula) ? (int) $aula : null,
            );
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        }

        if ($estado === null) {
            return $this->noEncontrado('Acceso no encontrado.');
        }

        return response()->json([
            'estado' => mb_strtolower($estado),
            'message' => $accion === 'trasladar' && $estado === 'AUTORIZADO'
                ? 'Acceso movido de aula.'
                : (self::AVISOS[$estado] ?? 'El acceso ya estaba cerrado.'),
        ]);
    }

    public function mover(int $examen, MoverEnElExamenRequest $request, MoverEnElExamen $mover): JsonResponse
    {
        $usuario = $request->validated('usuario_id');

        try {
            $movidos = $mover->execute(
                $examen,
                (int) $request->integer('de_aula_id'),
                (int) $request->integer('a_aula_id'),
                $this->usuarioId() ?? 0,
                is_numeric($usuario) ? (int) $usuario : null,
            );
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (DatoNoValidoException $error) {
            return $this->noValido($error);
        }

        return response()->json([
            'movidos' => $movidos,
            'message' => $usuario !== null
                ? 'Cambio de aula registrado.'
                : sprintf('%d %s de aula.', $movidos, $movidos === 1 ? 'estudiante cambió' : 'estudiantes cambiaron'),
        ]);
    }
}
