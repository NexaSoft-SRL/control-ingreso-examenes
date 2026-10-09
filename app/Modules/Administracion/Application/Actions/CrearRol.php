<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\RolesGateway;
use App\Modules\Administracion\Application\Contracts\Transaccion;
use App\Modules\Administracion\Application\DTOs\RolData;

/**
 * Un rol nuevo con los permisos que se le marcaron. Quien llama valida
 * antes que el nombre este libre y que los permisos existan.
 */
final readonly class CrearRol
{
    public function __construct(
        private RolesGateway $roles,
        private BitacoraGateway $bitacora,
        private Transaccion $transaccion,
    ) {}

    /**
     * @param  list<string>  $permisos
     */
    public function execute(string $nombre, array $permisos, ?int $administradorId): RolData
    {
        return $this->transaccion->ejecutar(function () use (
            $nombre,
            $permisos,
            $administradorId,
        ): RolData {
            $rol = $this->roles->crear($nombre, $permisos);

            $this->bitacora->registrar(
                $administradorId,
                'rol.crear',
                'roles',
                $rol->id,
                sprintf('Rol %s creado con %d permisos.', $rol->nombre, count($rol->permisos)),
            );

            return $rol;
        });
    }
}
