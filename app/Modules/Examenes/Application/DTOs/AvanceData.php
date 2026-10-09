<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Los cuatro pasos del examen (`ok`, `ahora` o `falta`), su estado y el
 * paso que sigue.
 */
final readonly class AvanceData
{
    public function __construct(
        public string $grupos,
        public string $aulas,
        public string $habilitacion,
        public string $qr,
        public string $estado,
        public string $accion,
        public ?int $paso,
    ) {}
}
