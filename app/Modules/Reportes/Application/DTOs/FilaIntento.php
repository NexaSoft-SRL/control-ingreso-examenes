<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Un intento que la puerta rechazo.
 */
final readonly class FilaIntento
{
    public function __construct(
        public string $ocurridaEn,
        public ?string $codigoUniversitario,
        public ?string $estudiante,
        public ?string $aula,
        public ?string $examenIntentado,
        public ?string $examenCorrespondiente,
        public ?string $causa,
    ) {}
}
