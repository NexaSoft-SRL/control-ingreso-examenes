<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Un aula del examen tal como llega del asistente: quien queda a cargo
 * (nulo = quien registra) y quienes apoyan.
 */
final readonly class AulaDeExamenData
{
    /**
     * @param  list<int>  $apoyo
     */
    public function __construct(
        public int $aulaId,
        public ?int $encargadoId,
        public array $apoyo,
    ) {}
}
