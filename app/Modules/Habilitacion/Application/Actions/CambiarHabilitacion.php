<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\CambioHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\ResultadoCambioData;
use App\Modules\Habilitacion\Domain\Exceptions\EstudianteConIngresoException;
use App\Modules\Habilitacion\Domain\Exceptions\EstudianteNoInscritoException;
use App\Modules\Habilitacion\Domain\Exceptions\ExamenAjenoException;

/**
 * «Habilitar» / «Inhabilitar» en lote. Habilitar no toca el aula;
 * inhabilitar la libera y exige el motivo.
 */
final readonly class CambiarHabilitacion
{
    public function __construct(
        private HabilitacionGateway $habilitaciones,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * @throws ExamenAjenoException
     * @throws EstudianteNoInscritoException
     * @throws EstudianteConIngresoException
     */
    public function execute(
        int $examenId,
        CambioHabilitacionData $cambio,
        int $usuarioId,
    ): ResultadoCambioData {
        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        if ($cambio->todos) {
            $estudiantes = $this->habilitaciones->estudiantesFiltrados($examenId, $cambio->filtros);
        } else {
            $estudiantes = array_values(array_unique($cambio->estudiantes));

            $ajenos = $this->habilitaciones->cuantosNoInscritos($examenId, $estudiantes);

            if ($ajenos > 0) {
                throw new EstudianteNoInscritoException($ajenos);
            }
        }

        if ($cambio->habilitado) {
            $afectados = $this->habilitaciones->habilitar($examenId, $estudiantes, $usuarioId);
        } else {
            $conIngreso = $this->habilitaciones->cuantosConIngreso($examenId, $estudiantes);

            if ($conIngreso > 0) {
                throw new EstudianteConIngresoException($conIngreso);
            }

            $afectados = $this->habilitaciones->inhabilitar(
                $examenId,
                $estudiantes,
                $cambio->motivo ?? '',
                $usuarioId,
            );
        }

        return new ResultadoCambioData(
            habilitado: $cambio->habilitado,
            afectados: $afectados,
            cifras: $this->habilitaciones->cifras($examenId),
        );
    }
}
