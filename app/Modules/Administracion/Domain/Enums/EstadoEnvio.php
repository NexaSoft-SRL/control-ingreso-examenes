<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Enums;

/**
 * El recorrido de un correo: en cola, enviado o fallido con su error.
 */
enum EstadoEnvio: string
{
    case Pendiente = 'PENDIENTE';
    case Enviado = 'ENVIADO';
    case Fallido = 'FALLIDO';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $estado): string => $estado->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Enviado => 'Enviado',
            self::Fallido => 'Fallido',
        };
    }
}
