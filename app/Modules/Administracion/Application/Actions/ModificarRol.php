<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\RolesGateway;
use App\Modules\Administracion\Application\Contracts\Transaccion;
use App\Modules\Administracion\Application\DTOs\RolData;
use App\Modules\Administracion\Domain\Exceptions\PermisoPropioException;
use App\Modules\Administracion\Domain\Exceptions\RolDeInicioException;

/**
 * Cambia los permisos de un rol y, si es un rol creado, su nombre. Las
 * cuentas del rol ganan o pierden los accesos en su siguiente peticion:
 * el permiso se lee cada vez.
 */
final readonly class ModificarRol
{
    private const PERMISO_DE_ESTA_PANTALLA = 'usuarios_roles';

    public function __construct(
        private RolesGateway $roles,
        private BitacoraGateway $bitacora,
        private Transaccion $transaccion,
    ) {}

    /**
     * Devuelve null si el rol no existe.
     *
     * @param  string|null  $nombre  null = el nombre no viaja y no se toca.
     * @param  list<string>  $permisos
     *
     * @throws RolDeInicioException
     * @throws PermisoPropioException
     */
    public function execute(
        int $rolId,
        ?string $nombre,
        array $permisos,
        ?int $administradorId,
    ): ?RolData {
        return $this->transaccion->ejecutar(function () use (
            $rolId,
            $nombre,
            $permisos,
            $administradorId,
        ): ?RolData {
            $antes = $this->roles->buscar($rolId, paraEscribir: true);

            if ($antes === null) {
                return null;
            }

            $nombre ??= $antes->nombre;

            if ($antes->esSistema && $nombre !== $antes->nombre) {
                throw new RolDeInicioException('El nombre de un rol de inicio no cambia.');
            }

            // Quien administra no se deja a si mismo fuera de esta pantalla.
            if (
                $administradorId !== null
                && $this->roles->rolDeUsuario($administradorId) === $rolId
                && ! in_array(self::PERMISO_DE_ESTA_PANTALLA, $permisos, true)
            ) {
                throw new PermisoPropioException(
                    'No puedes quitar «Usuarios y roles» a tu propio rol.'
                );
            }

            $despues = $this->roles->modificar($rolId, $nombre, $permisos);

            if ($despues === null) {
                return null;
            }

            if ($despues->nombre !== $antes->nombre || $despues->permisos !== $antes->permisos) {
                $this->bitacora->registrar(
                    $administradorId,
                    'rol.modificar',
                    'roles',
                    $rolId,
                    sprintf(
                        'El rol %s quedó con %d permisos.',
                        $despues->nombre,
                        count($despues->permisos),
                    ),
                );
            }

            return $despues;
        });
    }
}
