<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Como se identifico al estudiante en la puerta.
 */
enum MedioIdentificacion: string
{
    case Qr = 'QR';
    case Codigo = 'CODIGO';
    case Documento = 'DOCUMENTO';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $medio): string => $medio->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Qr => 'Código QR',
            self::Codigo => 'Código SIS',
            self::Documento => 'C.I.',
        };
    }
}
