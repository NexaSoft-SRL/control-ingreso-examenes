<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Enums;

/**
 * Por que la puerta rechazo un intento, tal como lo dice el reporte de
 * intentos rechazados. Los valores son los de `incidencias.causa`.
 */
enum MotivoIntento: string
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
            static fn (self $motivo): string => $motivo->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::NoInscrito => 'No inscrito en la materia',
            self::NoHabilitado => 'No habilitado',
            self::CodigoInvalido => 'Código no válido',
        };
    }
}
