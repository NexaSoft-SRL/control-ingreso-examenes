<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\Contracts\EquipoDeExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\EquipoDeExamenData;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;

/**
 * Un docente del examen (quien lo registro o el de un grupo suyo) suma a uno
 * de sus auxiliares: queda de apoyo en todas las aulas y atiende cualquiera.
 * Si ya estaba en el equipo no pasa nada.
 */
final readonly class SumarAlEquipoDeExamen
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
     * @throws DatoInvalidoException
     */
    public function execute(int $examenId, int $usuarioId, int $porId): ?array
    {
        if (! $this->examenes->existe($examenId)) {
            return null;
        }

        if (! $this->alcance->esDocenteDelExamen($porId, $examenId)) {
            throw new ExamenAjenoException;
        }

        if (! $this->equipo->esAuxiliarActivo($usuarioId)) {
            throw new DatoInvalidoException('usuario_id', 'Elija a un auxiliar con cuenta activa.');
        }

        if (! $this->equipo->tieneAulas($examenId)) {
            throw new DatoInvalidoException('usuario_id', 'El examen aún no tiene aulas: elíjalas antes de sumar a alguien.');
        }

        $this->equipo->sumar($examenId, $usuarioId, $porId);

        $detalle = $this->consulta->detalle($examenId, $porId);

        return $detalle === null ? [] : $detalle->equipo;
    }
}
