<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Enums;

/**
 * Los correos que envia el sistema.
 */
enum TipoCorreo: string
{
    case Credenciales = 'CREDENCIALES';
    case Restablecimiento = 'RESTABLECIMIENTO';
    case CodigoQr = 'CODIGO_QR';
    case Aviso = 'AVISO';

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
            self::Credenciales => 'Credenciales',
            self::Restablecimiento => 'Restablecimiento',
            self::CodigoQr => 'Código QR',
            self::Aviso => 'Aviso de examen',
        };
    }
}
