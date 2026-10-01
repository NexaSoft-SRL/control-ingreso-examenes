<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

final readonly class EstudianteHabilitacionData
{
    public function __construct(
        public int $id,
        public ?string $codigoUniversitario,
        public string $ci,
        public string $nombre,
        public string $apellido,
        public ?string $carrera,
        public string $condicion,
        public ?string $motivo,
        public ?string $registradoPor,
        public ?string $fechaHabilitacion,
    ) {}

    /** @return array{id: int, codigo_universitario: ?string, ci: string, nombre: string, apellido: string, carrera: ?string, condicion: string, motivo: ?string, registrado_por: ?string, fecha_habilitacion: ?string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo_universitario' => $this->codigoUniversitario,
            'ci' => $this->ci,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'carrera' => $this->carrera,
            'condicion' => $this->condicion,
            'motivo' => $this->motivo,
            'registrado_por' => $this->registradoPor,
            'fecha_habilitacion' => $this->fechaHabilitacion,
        ];
    }
}
