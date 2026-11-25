<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\Contracts\InvitacionExamenGateway;
use App\Modules\Examenes\Application\DTOs\InvitacionData;
use App\Modules\Examenes\Application\DTOs\InvitacionDeExamenData;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Examenes\Domain\Exceptions\ExamenConIngresosException;
use App\Modules\Examenes\Domain\Exceptions\GrupoYaTieneExamenException;
use App\Modules\Examenes\Domain\Exceptions\InvitacionAjenaException;
use App\Modules\Examenes\Domain\Exceptions\InvitacionYaRespondidaException;
use App\Modules\Examenes\Domain\Rules\AvanceDeExamen;

/**
 * El docente acepta o rechaza que su grupo se sume al examen de otro. Solo
 * lo responde el docente del grupo y solo una invitacion pendiente. Aceptar
 * pide que el examen no tenga ingresos y que el grupo no tenga ya otro
 * examen de ese tipo.
 */
final readonly class ResponderInvitacionDeExamen
{
    public function __construct(
        private InvitacionExamenGateway $invitaciones,
        private ExamenGateway $examenes,
        private ConsultaExamenGateway $consulta,
    ) {}

    /**
     * @return bool false si la invitacion no existe
     *
     * @throws InvitacionAjenaException
     * @throws InvitacionYaRespondidaException
     * @throws ExamenConIngresosException
     * @throws GrupoYaTieneExamenException
     */
    public function aceptar(int $invitacionId, int $usuarioId): bool
    {
        $invitacion = $this->pendiente($invitacionId, $usuarioId);

        if ($invitacion === null) {
            return false;
        }

        if ($this->examenes->tieneIngresos($invitacion->examenId)) {
            throw new ExamenConIngresosException;
        }

        $ocupados = $this->consulta->gruposConExamenDeTipo([$invitacion->grupoId], $invitacion->tipo, $invitacion->examenId);

        if ($ocupados !== []) {
            throw new GrupoYaTieneExamenException(
                $ocupados[0],
                AvanceDeExamen::tipoConArticulo(TipoExamen::from($invitacion->tipo)),
            );
        }

        $this->invitaciones->aceptar($invitacionId, $usuarioId);

        return true;
    }

    /**
     * @return bool false si la invitacion no existe
     *
     * @throws InvitacionAjenaException
     * @throws InvitacionYaRespondidaException
     */
    public function rechazar(int $invitacionId, int $usuarioId): bool
    {
        if ($this->pendiente($invitacionId, $usuarioId) === null) {
            return false;
        }

        $this->invitaciones->rechazar($invitacionId, $usuarioId);

        return true;
    }

    /**
     * @throws InvitacionAjenaException
     * @throws InvitacionYaRespondidaException
     */
    private function pendiente(int $invitacionId, int $usuarioId): ?InvitacionData
    {
        $invitacion = $this->invitaciones->encontrar($invitacionId);

        if ($invitacion === null) {
            return null;
        }

        if ($invitacion->docenteUsuarioId !== $usuarioId) {
            throw new InvitacionAjenaException;
        }

        if ($invitacion->estado !== InvitacionDeExamenData::PENDIENTE) {
            throw new InvitacionYaRespondidaException;
        }

        return $invitacion;
    }
}
