<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Por que la puerta rechazo un intento. Solo en incidencias del sistema.
 */
enum CausaIntento: string
{
    case NoInscrito = 'NO_INSCRITO';
    case NoHabilitado = 'NO_HABILITADO';
    case CodigoInvalido = 'CODIGO_INVALIDO';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $causa): string => $causa->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::NoInscrito => 'No inscrito',
            self::NoHabilitado => 'No habilitado',
            self::CodigoInvalido => 'Código inválido',
        };
    }
}
