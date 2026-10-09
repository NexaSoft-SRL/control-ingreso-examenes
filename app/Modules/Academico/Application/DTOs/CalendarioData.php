<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Lo que un calendario academico publicado dice de un periodo.
 */
final readonly class CalendarioData
{
    /**
     * @param  string  $periodo  `2/2026`
     * @param  string|null  $inicio  `AAAA-MM-DD`
     * @param  string|null  $fin  `AAAA-MM-DD`
     * @param  array<string, mixed>  $ventanas  tal cual la fuente
     */
    public function __construct(
        public string $periodo,
        public ?string $inicio,
        public ?string $fin,
        public array $ventanas,
        public string $fuente,
    ) {}
}
