<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Un rol como lo muestra la matriz de permisos.
 */
final readonly class RolData
{
    /**
     * @param  list<string>  $permisos  Claves, en el orden del catalogo.
     */
    public function __construct(
        public int $id,
        public string $nombre,
        // true en los tres de inicio: no se renombran ni se eliminan.
        public bool $esSistema,
        public int $cuentas,
        public array $permisos,
    ) {}
}
