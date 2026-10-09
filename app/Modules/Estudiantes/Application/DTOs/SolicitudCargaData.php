<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

use App\Modules\Estudiantes\Domain\Enums\AlcanceCarga;

/**
 * El archivo subido y para que se sube: la lista de un grupo (docente) o
 * las inscripciones de una facultad (administracion).
 */
final readonly class SolicitudCargaData
{
    private function __construct(
        public AlcanceCarga $alcance,
        public ?int $grupoId,
        public ?string $facultad,
        public string $rutaArchivo,
        public string $extension,
        public string $nombreArchivo,
        public int $usuarioId,
    ) {}

    public static function deGrupo(
        int $grupoId,
        string $rutaArchivo,
        string $extension,
        string $nombreArchivo,
        int $usuarioId,
    ): self {
        return new self(
            AlcanceCarga::Grupo,
            $grupoId,
            null,
            $rutaArchivo,
            $extension,
            $nombreArchivo,
            $usuarioId,
        );
    }

    public static function deFacultad(
        string $facultad,
        string $rutaArchivo,
        string $extension,
        string $nombreArchivo,
        int $usuarioId,
    ): self {
        return new self(
            AlcanceCarga::Facultad,
            null,
            $facultad,
            $rutaArchivo,
            $extension,
            $nombreArchivo,
            $usuarioId,
        );
    }
}
