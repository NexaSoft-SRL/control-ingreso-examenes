<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Una norma que se puede marcar al registrar un examen. `usuarioId` nulo =
 * norma predefinida del sistema.
 */
final readonly class PlantillaNormaData
{
    public function __construct(
        public int $id,
        public string $texto,
        public ?int $usuarioId,
    ) {}

    public function esPredefinida(): bool
    {
        return $this->usuarioId === null;
    }

    public function esDe(int $usuarioId): bool
    {
        return $this->usuarioId === $usuarioId;
    }
}
