<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Authorization;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;

/**
 * El alcance, ademas del permiso: el docente del examen controla
 * cualquiera de sus aulas; quien esta a cargo o de apoyo, solo la suya y
 * solo el dia del examen; quien entro con codigo de aula, solo esa aula.
 */
final readonly class AlcancePuesto
{
    public function __construct(
        private AlcanceExamenGateway $alcance,
    ) {}

    public function esDocente(?int $usuarioId, int $examenId): bool
    {
        return $usuarioId !== null
            && $this->alcance->esDocenteDelExamen($usuarioId, $examenId);
    }

    public function exigirDocente(?int $usuarioId, int $examenId): void
    {
        if (! $this->esDocente($usuarioId, $examenId)) {
            throw FueraDeAlcanceException::examen();
        }
    }

    /**
     * La puerta trabaja por aula: quien atiende alguno de los examenes
     * del dia en esa aula opera la puerta entera.
     *
     * @param  list<ExamenData>  $examenesDelDia
     */
    public function atiendeAula(OperadorData $operador, int $aulaId, array $examenesDelDia): bool
    {
        if ($operador->usuarioId === null) {
            return $operador->aulaId === $aulaId;
        }

        foreach ($examenesDelDia as $examen) {
            if (
                $this->alcance->esDocenteDelExamen($operador->usuarioId, $examen->id)
                || $this->alcance->atiendeAula($operador->usuarioId, $examen->id, $aulaId)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sobre un examen concreto y una de sus aulas.
     */
    public function actuaEnExamen(OperadorData $operador, ExamenData $examen, int $aulaId): bool
    {
        if ($operador->usuarioId === null) {
            return $operador->aulaId === $aulaId;
        }

        if ($this->alcance->esDocenteDelExamen($operador->usuarioId, $examen->id)) {
            return true;
        }

        return $examen->esHoy
            && $this->alcance->atiendeAula($operador->usuarioId, $examen->id, $aulaId);
    }
}
