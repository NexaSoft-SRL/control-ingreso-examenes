<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Enums;

/**
 * Las dos salidas de un conflicto. Con cualquiera se crea la inscripcion
 * que habia quedado en espera.
 */
enum ResolucionConflicto: string
{
    case MantenerPadron = 'MANTENER_PADRON';
    case UsarCarga = 'USAR_CARGA';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $resolucion): string => $resolucion->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::MantenerPadron => 'Mantener el padrón',
            self::UsarCarga => 'Usar la carga',
        };
    }
}
