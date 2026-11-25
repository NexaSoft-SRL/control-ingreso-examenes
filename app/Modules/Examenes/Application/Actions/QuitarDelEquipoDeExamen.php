<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\Contracts\EquipoDeExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\EquipoDeExamenData;
use App\Modules\Examenes\Domain\Exceptions\EquipoACargoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Examenes\Domain\Exceptions\NoEstaEnElEquipoException;

/**
 * Saca a alguien del equipo. Quien registro el examen quita a cualquiera; un
 * docente colaborador, solo a quien el mismo sumo. Quien esta a cargo de un
 * aula no sale hasta que se cambie.
 */
final readonly class QuitarDelEquipoDeExamen
{
    public function __construct(
        private EquipoDeExamenGateway $equipo,
        private ExamenGateway $examenes,
        private AlcanceExamenGateway $alcance,
        private ConsultaExamenGateway $consulta,
    ) {}

    /**
     * @return list<EquipoDeExamenData>|null nulo si el examen no existe
     *
     * @throws ExamenAjenoException
     * @throws NoEstaEnElEquipoException
     * @throws EquipoACargoException
     */
    public function execute(int $examenId, int $usuarioId, int $porId): ?array
    {
        if (! $this->examenes->existe($examenId)) {
            return null;
        }

        if (! $this->alcance->esDocenteDelExamen($porId, $examenId)) {
            throw new ExamenAjenoException;
        }

        if (! $this->equipo->estaEnElEquipo($examenId, $usuarioId)) {
            throw new NoEstaEnElEquipoException;
        }

        $loSumoEl = $this->equipo->agregadoPor($examenId, $usuarioId) === $porId;

        if (! $this->alcance->loRegistro($porId, $examenId) && ! $loSumoEl) {
            throw new ExamenAjenoException;
        }

        if ($this->equipo->estaACargo($examenId, $usuarioId)) {
            throw new EquipoACargoException;
        }

        $this->equipo->quitar($examenId, $usuarioId, $porId);

        $detalle = $this->consulta->detalle($examenId, $porId);

        return $detalle === null ? [] : $detalle->equipo;
    }
}
