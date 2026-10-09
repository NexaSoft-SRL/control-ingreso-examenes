<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * El listado del docente con el periodo consultado y la hora del servidor.
 */
final readonly class ListadoExamenesData
{
    /**
     * @param  list<ExamenResumenData>  $examenes
     */
    public function __construct(
        public array $examenes,
        public ?string $periodo,
        public string $hoy,
        public string $horaServidor,
    ) {}
}
