<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Queries;

use App\Modules\Administracion\Application\Contracts\ConsultaUsuariosGateway;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;

/**
 * La matriz de permisos por rol, de solo lectura: los roles son tres y de
 * sistema, y la pantalla la muestra como una tabla fija.
 */
final readonly class ConsultarMatrizPermisos
{
    public function __construct(
        private ConsultaUsuariosGateway $usuarios,
    ) {}

    /**
     * @return array{roles: list<string>, pantallas: list<array{pantalla: string, roles: list<string>}>}
     */
    public function execute(): array
    {
        /** @var array<string, array<string, true>> $porPantalla */
        $porPantalla = [];

        foreach ($this->usuarios->permisosPorPantalla() as $permiso) {
            // Dos permisos pueden ser la misma pantalla vista por roles
            // distintos («Padrón e inscripciones» y «… (sus grupos)»): la
            // matriz los muestra en una sola fila.
            $pantalla = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $permiso->pantalla));

            $porPantalla[$pantalla] ??= [];

            foreach ($permiso->roles as $rol) {
                $porPantalla[$pantalla][$rol] = true;
            }
        }

        $pantallas = [];

        foreach ($porPantalla as $pantalla => $roles) {
            $pantallas[] = [
                'pantalla' => (string) $pantalla,
                // En el orden fijo de los roles, no en el de la base.
                'roles' => array_values(array_filter(
                    NuevaCuentaData::ROLES,
                    static fn (string $rol): bool => isset($roles[$rol]),
                )),
            ];
        }

        return [
            'roles' => NuevaCuentaData::ROLES,
            'pantallas' => $pantallas,
        ];
    }
}
