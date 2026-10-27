<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Authorization;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\DTOs\OperadorPuertaData;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;

/**
 * El alcance de quien opera la puerta o sigue el examen, ademas del
 * permiso: el docente del examen alcanza todas sus aulas; quien esta a
 * cargo o de apoyo, solo la suya; quien entro con codigo de aula, solo esa
 * aula.
 */
final readonly class AlcancePuerta
{
    public function __construct(
        private AlcanceExamenGateway $examenes,
        private AlcancePuntoControl $puerta,
    ) {}

    public function esDocente(?int $usuarioId, int $examenId): bool
    {
        return $usuarioId !== null
            && $this->examenes->esDocenteDelExamen($usuarioId, $examenId);
    }

    public function exigirDocente(?int $usuarioId, int $examenId): void
    {
        if (! $this->esDocente($usuarioId, $examenId)) {
            throw new AccesoPuntoDenegado('Solo el docente del examen.');
        }
    }

    /**
     * Docente del examen o personal asignado al aula (a cargo o apoyo).
     */
    public function atiendeAula(?int $usuarioId, int $examenId, int $aulaId): bool
    {
        return $usuarioId !== null && (
            $this->esDocente($usuarioId, $examenId)
            || in_array($aulaId, $this->examenes->aulasQueAtiende($usuarioId, $examenId), true)
        );
    }

    /**
     * Las aulas del examen que la cuenta atiende (a cargo o apoyo).
     *
     * @return list<int>
     */
    public function aulasQueAtiende(int $usuarioId, int $examenId): array
    {
        return $this->examenes->aulasQueAtiende($usuarioId, $examenId);
    }

    /**
     * La regla unica de la puerta («puede operar»); con codigo de aula,
     * solo el aula del acceso.
     */
    public function puedeOperar(OperadorPuertaData $operador, int $examenId, int $aulaId): bool
    {
        if ($operador->esAcceso()) {
            return $operador->aulaId === $aulaId;
        }

        return $operador->usuarioId !== null
            && $this->puerta->puedeOperar($operador->usuarioId, $examenId, $aulaId);
    }

    /**
     * Quien puede dejar una incidencia en un aula del examen.
     */
    public function actuaEnAula(OperadorPuertaData $operador, int $examenId, int $aulaId): bool
    {
        return $operador->esAcceso()
            ? $operador->aulaId === $aulaId
            : $this->atiendeAula($operador->usuarioId, $examenId, $aulaId);
    }
}
