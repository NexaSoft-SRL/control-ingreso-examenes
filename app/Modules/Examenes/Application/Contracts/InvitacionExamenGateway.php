<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\InvitacionData;
use App\Modules\Examenes\Application\DTOs\InvitacionRecibidaData;

/**
 * Las invitaciones a sumar un grupo a un examen de otro docente. Crearlas y
 * quitarlas es parte de guardar el examen (`ExamenGateway`).
 */
interface InvitacionExamenGateway
{
    /**
     * Las pendientes de los grupos del docente, por fecha y hora del examen.
     *
     * @return list<InvitacionRecibidaData>
     */
    public function pendientesDe(int $usuarioId): array;

    public function encontrar(int $invitacionId): ?InvitacionData;

    /**
     * Suma el grupo al examen y borra la invitacion. Asienta
     * `examen.aceptar_invitacion`.
     */
    public function aceptar(int $invitacionId, int $usuarioId): void;

    /**
     * La deja `rechazada`. Asienta `examen.rechazar_invitacion`.
     */
    public function rechazar(int $invitacionId, int $usuarioId): void;

    /**
     * Los grupos del examen cuyo docente es esa cuenta.
     *
     * @return list<int>
     */
    public function gruposDelDocenteEnExamen(int $examenId, int $usuarioId): array;

    /**
     * Saca del examen esos grupos y deja sin habilitacion ni codigo QR a
     * quienes ya no estan inscritos en ningun grupo del examen. Asienta
     * `examen.salir`.
     *
     * @param  list<int>  $grupoIds
     */
    public function salir(int $examenId, array $grupoIds, int $usuarioId): void;
}
