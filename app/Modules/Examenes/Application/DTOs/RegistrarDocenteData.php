<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

final readonly class RegistrarDocenteData
{
    public function __construct(
        public string $codigoDocente,
        public string $nombres,
        public string $apellidos,
        public ?string $correo = null,
        public ?string $telefono = null,
        public ?int $cuentaId = null,
    ) {}
}
