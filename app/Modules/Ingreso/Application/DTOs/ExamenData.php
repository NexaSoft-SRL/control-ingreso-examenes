<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Un examen visto desde la puerta. `abierto`: la hora del servidor esta
 * entre la apertura (60 min antes del inicio) y el final. Las cifras son
 * las del aula por la que se pregunta (cero si no se pregunto por aula).
 * `normasMarcadas` son los textos de las normas elegidas por plantilla, en
 * su orden; `normas`, el texto libre.
 */
final readonly class ExamenData
{
    /**
     * @param  list<string>  $normasMarcadas
     */
    public function __construct(
        public int $id,
        public string $asignatura,
        public string $tipo,
        public string $fecha,
        public string $hora,
        public bool $abierto,
        public bool $esHoy,
        public string $hasta,
        public ?string $normas = null,
        public int $ingresados = 0,
        public int $asignados = 0,
        public array $normasMarcadas = [],
        public int $faltan = 0,
    ) {}

    /**
     * «Primer parcial · Introduccion a la Programacion».
     */
    public function texto(): string
    {
        return $this->tipo.' · '.$this->asignatura;
    }
}
