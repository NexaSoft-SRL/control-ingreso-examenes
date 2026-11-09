<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\AulaDeExamenData;
use App\Modules\Examenes\Application\DTOs\ExamenActualData;
use App\Modules\Examenes\Application\DTOs\ExamenDetalleData;
use App\Modules\Examenes\Application\DTOs\GuardarExamenData;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenConIngresosException;

/**
 * Solo quien registro el examen lo modifica. Con ingresos registrados se
 * pueden cambiar las normas (las marcadas y el texto libre) y el personal
 * de las aulas, nada mas.
 */
final readonly class ActualizarExamen
{
    public function __construct(
        private ComprobarDatosDeExamen $comprobar,
        private ExamenGateway $examenes,
        private ConsultaExamenGateway $consulta,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * @throws DatoInvalidoException
     * @throws ExamenAjenoException
     * @throws ExamenConIngresosException
     */
    public function execute(int $examenId, GuardarExamenData $datos, int $usuarioId): ?ExamenDetalleData
    {
        $actual = $this->examenes->actual($examenId);

        if ($actual === null) {
            return null;
        }

        if (! $this->alcance->loRegistro($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        $conIngresos = $this->examenes->tieneIngresos($examenId);

        if ($conIngresos && $this->cambiaAlgoMasQueElPersonal($actual, $datos)) {
            throw new ExamenConIngresosException;
        }

        $guardado = $this->examenes->actualizar(
            $examenId,
            $this->comprobar->execute($datos, $usuarioId, $actual, $conIngresos),
            $usuarioId,
        );

        return $guardado ? $this->consulta->detalle($examenId, $usuarioId) : null;
    }

    private function cambiaAlgoMasQueElPersonal(ExamenActualData $actual, GuardarExamenData $datos): bool
    {
        $aulas = array_map(
            static fn (AulaDeExamenData $aula): int => $aula->aulaId,
            $datos->aulas,
        );

        return $actual->asignaturaId !== $datos->asignaturaId
            || $actual->tipo !== $datos->tipo
            || $actual->fecha !== $datos->fecha
            || $actual->horaInicio !== $datos->horaInicio
            || $actual->duracionMinutos !== $datos->duracionMinutos
            || $this->ordenados($actual->grupos) !== $this->ordenados($datos->grupos)
            || $this->ordenados($actual->aulas) !== $this->ordenados($aulas);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function ordenados(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
