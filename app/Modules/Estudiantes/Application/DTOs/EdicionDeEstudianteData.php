<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Los datos corregidos de un estudiante del padron. El codigo SIS solo lo
 * cambia la administracion (`$codigo`, null = no se toca); el docente no.
 */
final readonly class EdicionDeEstudianteData
{
    public function __construct(
        public int $usuarioId,
        public int $estudianteId,
        public string $documento,
        public string $nombres,
        public string $apellidos,
        public ?string $codigo = null,
    ) {}
}
