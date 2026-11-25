<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\QuitarDelEquipoDeExamen;
use App\Modules\Examenes\Application\Actions\SumarAlEquipoDeExamen;
use App\Modules\Examenes\Application\DTOs\EquipoDeExamenData;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Domain\Exceptions\EquipoACargoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Examenes\Domain\Exceptions\NoEstaEnElEquipoException;
use App\Modules\Examenes\Http\Requests\SumarAlEquipoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * El equipo del examen: cada docente del examen suma y quita a sus
 * auxiliares.
 */
final class EquipoExamenController
{
    use RespuestasDeExamen;

    public function sumar(SumarAlEquipoRequest $request, int $examen, SumarAlEquipoDeExamen $sumar): JsonResponse
    {
        try {
            $equipo = $sumar->execute($examen, $request->usuarioId(), $this->usuarioId());
        } catch (ExamenAjenoException) {
            return $this->ajeno();
        } catch (DatoInvalidoException $error) {
            return $this->invalido($error);
        }

        return $equipo === null
            ? $this->noEncontrado()
            : $this->equipo($equipo, 'Equipo actualizado.');
    }

    public function quitar(int $examen, int $usuario, QuitarDelEquipoDeExamen $quitar): JsonResponse
    {
        try {
            $equipo = $quitar->execute($examen, $usuario, $this->usuarioId());
        } catch (ExamenAjenoException) {
            return $this->ajeno();
        } catch (NoEstaEnElEquipoException $error) {
            return $this->noEncontrado($error->getMessage());
        } catch (EquipoACargoException $error) {
            return response()->json([
                'message' => $error->getMessage(),
                'codigo' => 'EQUIPO_A_CARGO',
            ], Response::HTTP_CONFLICT);
        }

        return $equipo === null
            ? $this->noEncontrado()
            : $this->equipo($equipo, 'Equipo actualizado.');
    }

    /**
     * @param  list<EquipoDeExamenData>  $equipo
     */
    private function equipo(array $equipo, string $mensaje): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'data' => [
                'equipo' => array_map(
                    static fn (EquipoDeExamenData $persona): array => [
                        'id' => $persona->id,
                        'nombre' => $persona->nombre,
                        'es_docente' => $persona->esDocente,
                        'es_auxiliar' => $persona->esAuxiliar,
                        'a_cargo' => $persona->aCargo,
                        'agregado_por' => $persona->agregadoPor,
                        'agregado_por_nombre' => $persona->agregadoPorNombre,
                        'puede_quitar' => $persona->puedeQuitar,
                    ],
                    $equipo,
                ),
            ],
        ]);
    }
}
