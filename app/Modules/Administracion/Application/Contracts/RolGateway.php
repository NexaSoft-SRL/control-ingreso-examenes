<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

/**
 * Asignacion de un rol a una cuenta y lectura de los permisos que ese rol
 * le da. El alta, la edicion y la eliminacion de roles van por su propio
 * contrato.
 */
interface RolGateway
{
    public function asignarRolAUsuario(int $usuarioId, ?int $rolId): bool;

    /**
     * Claves de permiso del rol que tenga el usuario, en el orden del
     * catalogo.
     *
     * @return list<string>
     */
    public function permisosDe(int $usuarioId): array;
}
