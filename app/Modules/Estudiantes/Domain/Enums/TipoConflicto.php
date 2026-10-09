<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Enums;

/**
 * Por que una fila de la carga no coincide con el estudiante guardado.
 */
enum TipoConflicto: string
{
    case DocumentoDistinto = 'DOCUMENTO_DISTINTO';
    case NombreDistinto = 'NOMBRE_DISTINTO';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $tipo): string => $tipo->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::DocumentoDistinto => 'Documento distinto',
            self::NombreDistinto => 'Nombre distinto',
        };
    }
}
