<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Quien opera la puerta: una cuenta con sesion o alguien que entro con el
 * codigo de un aula. En el segundo caso no hay usuario y solo alcanza a
 * esa aula.
 */
final readonly class OperadorData
{
    public function __construct(
        public ?int $usuarioId,
        public ?int $accesoAulaId = null,
        public ?int $aulaId = null,
        public ?int $examenId = null,
        public ?string $aula = null,
        public ?string $nombre = null,
        public ?string $hasta = null,
    ) {}

    public function esAcceso(): bool
    {
        return $this->usuarioId === null;
    }
}
