<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

final readonly class ExamenHabilitacionData
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $fecha,
        public string $asignaturaCodigo,
        public string $codigoGrupo,
    ) {}

    /** @return array{id: int, nombre: string, fecha: string, asignatura_codigo: string, codigo_grupo: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'fecha' => $this->fecha,
            'asignatura_codigo' => $this->asignaturaCodigo,
            'codigo_grupo' => $this->codigoGrupo,
        ];
    }
}
