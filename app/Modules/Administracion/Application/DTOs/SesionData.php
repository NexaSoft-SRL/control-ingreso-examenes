<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Lo que el cliente necesita saber de quien tiene la sesion abierta.
 */
final readonly class SesionData
{
    /**
     * @param  list<string>  $permisos
     */
    public function __construct(
        public int $id,
        public string $nombre,
        public string $usuario,
        public ?string $correo,
        public ?string $rol,
        public array $permisos,
        public ?int $docenteId,
    ) {}
}
