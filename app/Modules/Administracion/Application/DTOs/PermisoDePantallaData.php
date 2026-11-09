<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Un permiso del catalogo con los roles que lo tienen vigente hoy.
 */
final readonly class PermisoDePantallaData
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $clave,
        public string $pantalla,
        public array $roles,
    ) {}
}
