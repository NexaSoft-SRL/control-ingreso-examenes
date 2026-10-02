<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

final readonly class RegistrarNormaExamenData
{
    public function __construct(
        public string $alcance,
        public string $texto,
        public ?int $estudianteId,
        public ?string $motivo,
    ) {}
}
