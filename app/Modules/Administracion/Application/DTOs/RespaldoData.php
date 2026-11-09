<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Una generacion de respaldo, correcta o fallida.
 */
final readonly class RespaldoData
{
    public const TIPO_PROGRAMADO = 'PROGRAMADO';

    public const TIPO_PUNTUAL = 'PUNTUAL';

    public const ESTADO_CORRECTO = 'CORRECTO';

    public const ESTADO_FALLIDO = 'FALLIDO';

    public function __construct(
        public int $id,
        // null si fallo antes de escribir el archivo.
        public ?string $archivo,
        public string $tipo,
        public string $estado,
        public ?int $tamanoBytes,
        public int $tablas,
        public int $filas,
        public ?string $error,
        // Instante ISO 8601 con zona.
        public string $generadoEn,
        // Verdadero solo en el ultimo respaldo desde el que se restauro.
        public bool $restaurado,
    ) {}

    public function esCorrecto(): bool
    {
        return $this->estado === self::ESTADO_CORRECTO;
    }
}
