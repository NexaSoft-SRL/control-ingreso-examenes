<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * Lo que el personal de control necesita para responder en la puerta
 * (HU-13): la condición, el ambiente y, si corresponde, el motivo.
 */
final readonly class ConsultaHabilitacionData
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
        public ?string $ambiente,
        public ?string $registradoPor,
        public ?string $fechaRegistro,
    ) {}

    /** @return array{id: int, codigo_universitario: ?string, ci: string, nombre: string, apellido: string, carrera: ?string, condicion: string, habilitado: bool, motivo: ?string, ambiente: ?string, registrado_por: ?string, fecha_registro: ?string} */
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
            'habilitado' => $this->condicion === 'HABILITADO',
            'motivo' => $this->motivo,
            'ambiente' => $this->ambiente,
            'registrado_por' => $this->registradoPor,
            'fecha_registro' => $this->fechaRegistro,
        ];
    }
}
