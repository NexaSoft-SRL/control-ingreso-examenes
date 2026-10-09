<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\RolData;

/**
 * Alta, edicion y baja de roles, y la lectura de la matriz de permisos.
 * La asignacion de un rol a una cuenta va por `RolGateway`.
 */
interface RolesGateway
{
    /**
     * Los roles de inicio primero y despues los creados, cada uno con sus
     * cuentas y sus permisos.
     *
     * @return list<RolData>
     */
    public function listar(): array;

    /**
     * El catalogo de permisos (clave => pantalla) en el orden de la matriz.
     *
     * @return array<string, string>
     */
    public function catalogo(): array;

    /**
     * Con `$paraEscribir` la fila queda tomada hasta el fin de la
     * transaccion: nadie le asigna cuentas mientras se decide su baja.
     */
    public function buscar(int $rolId, bool $paraEscribir = false): ?RolData;

    /**
     * No distingue mayusculas.
     */
    public function nombreRegistrado(string $nombre, ?int $exceptoId = null): bool;

    /**
     * Rol de la cuenta, o null si no tiene (o la cuenta no existe).
     */
    public function rolDeUsuario(int $usuarioId): ?int;

    /**
     * @param  list<string>  $permisos  Claves del catalogo.
     */
    public function crear(string $nombre, array $permisos): RolData;

    /**
     * Devuelve null si el rol no existe.
     *
     * @param  list<string>  $permisos  Claves del catalogo: reemplazan a las anteriores.
     */
    public function modificar(int $rolId, string $nombre, array $permisos): ?RolData;

    /**
     * Devuelve false si el rol no existe.
     */
    public function eliminar(int $rolId): bool;
}
