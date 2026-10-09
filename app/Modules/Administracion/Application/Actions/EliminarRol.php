<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\RolesGateway;
use App\Modules\Administracion\Application\Contracts\Transaccion;
use App\Modules\Administracion\Domain\Exceptions\RolConCuentasException;
use App\Modules\Administracion\Domain\Exceptions\RolDeInicioException;

/**
 * Baja de un rol creado que ninguna cuenta usa. Los tres de inicio no se
 * eliminan.
 */
final readonly class EliminarRol
{
    public function __construct(
        private RolesGateway $roles,
        private BitacoraGateway $bitacora,
        private Transaccion $transaccion,
    ) {}

    /**
     * Devuelve false si el rol no existe.
     *
     * @throws RolDeInicioException
     * @throws RolConCuentasException
     */
    public function execute(int $rolId, ?int $administradorId): bool
    {
        return $this->transaccion->ejecutar(function () use ($rolId, $administradorId): bool {
            $rol = $this->roles->buscar($rolId, paraEscribir: true);

            if ($rol === null) {
                return false;
            }

            if ($rol->esSistema) {
                throw new RolDeInicioException('Los roles de inicio no se eliminan.');
            }

            if ($rol->cuentas > 0) {
                throw new RolConCuentasException(
                    $rol->cuentas === 1
                        ? 'El rol tiene 1 cuenta asignada.'
                        : sprintf('El rol tiene %d cuentas asignadas.', $rol->cuentas)
                );
            }

            if (! $this->roles->eliminar($rolId)) {
                return false;
            }

            $this->bitacora->registrar(
                $administradorId,
                'rol.eliminar',
                'roles',
                $rolId,
                sprintf('Rol %s eliminado.', $rol->nombre),
            );

            return true;
        });
    }
}
