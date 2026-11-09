<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

/**
 * Los roles son tres y de sistema: ya no se crean, eliminan ni editan
 * desde la pantalla. Queda la asignacion a una cuenta y la lectura de sus
 * permisos; el listado de cuentas es de `ConsultaUsuariosGateway`.
 */
interface RolGateway
{
    public function asignarRolAUsuario(int $usuarioId, ?int $rolId): bool;

    /**
     * Permisos con vigencia al dia de hoy del rol que tenga el usuario.
     *
     * @return list<string>
     */
    public function permisosVigentesDe(int $usuarioId): array;
}
