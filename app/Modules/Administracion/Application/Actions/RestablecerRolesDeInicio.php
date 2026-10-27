<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\RolesGateway;
use App\Modules\Administracion\Application\Contracts\Transaccion;
use App\Modules\Administracion\Domain\Rules\PermisosDeInicio;

/**
 * Devuelve a los tres roles de inicio (Administrador, Docente, Auxiliar)
 * su reparto de fabrica, por si alguien los cambio al azar. Los roles
 * creados despues no se tocan.
 */
final readonly class RestablecerRolesDeInicio
{
    public function __construct(
        private RolesGateway $roles,
        private BitacoraGateway $bitacora,
        private Transaccion $transaccion,
    ) {}

    /**
     * Los nombres de los roles que cambiaron.
     *
     * @return list<string>
     */
    public function execute(?int $administradorId): array
    {
        return $this->transaccion->ejecutar(function () use ($administradorId): array {
            $cambiados = [];

            foreach ($this->roles->listar() as $rol) {
                $deFabrica = PermisosDeInicio::POR_ROL[$rol->nombre] ?? null;

                if (! $rol->esSistema || $deFabrica === null) {
                    continue;
                }

                $antes = $rol->permisos;
                $despues = $this->roles->modificar($rol->id, $rol->nombre, $deFabrica);

                if ($despues !== null && $despues->permisos !== $antes) {
                    $cambiados[] = $rol->nombre;
                }
            }

            $this->bitacora->registrar(
                $administradorId,
                'rol.restablecer',
                'roles',
                null,
                $cambiados === []
                    ? 'Roles de inicio revisados: ya tenían su reparto de fábrica.'
                    : 'Roles de inicio restablecidos: '.implode(', ', $cambiados).'.',
            );

            return $cambiados;
        });
    }
}
