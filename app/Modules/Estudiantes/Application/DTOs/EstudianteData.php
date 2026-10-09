<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Un estudiante del padron tal como lo necesitan las cargas y los demas
 * modulos.
 */
final readonly class EstudianteData
{
    public function __construct(
        public int $id,
        public string $codigoUniversitario,
        public ?string $documentoIdentidad,
        public string $nombres,
        public string $apellidos,
        public bool $verificado,
    ) {}

    public function nombreCompleto(): string
    {
        return trim("{$this->apellidos}, {$this->nombres}", ', ');
    }
}
