<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Lo que el docente escribe para agregar a un estudiante suelto a la lista
 * de su grupo: los mismos datos de una fila del archivo.
 */
final readonly class SolicitudAltaData
{
    public function __construct(
        public int $usuarioId,
        public int $grupoId,
        public string $codigoUniversitario,
        public string $documentoIdentidad,
        public string $nombres,
        public string $apellidos,
    ) {}
}
