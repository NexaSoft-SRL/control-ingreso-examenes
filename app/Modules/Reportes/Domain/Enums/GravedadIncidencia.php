<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Enums;

/**
 * La gravedad de una incidencia en los reportes y en la bitacora del
 * examen. Sale de `incidencias.tipo` igual que en el modulo Ingreso (una
 * prueba lo compara).
 */
enum GravedadIncidencia: string
{
    case MuyGrave = 'MUY_GRAVE';
    case Grave = 'GRAVE';
    case Leve = 'LEVE';

    public static function deTipo(string $tipo): self
    {
        return match ($tipo) {
            'SUPLANTACION', 'EXPULSION' => self::MuyGrave,
            'INGRESO_REPETIDO',
            'INTENTO_NO_AUTORIZADO',
            'MATERIAL_NO_PERMITIDO',
            'DISPOSITIVO_NO_PERMITIDO' => self::Grave,
            default => self::Leve,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::MuyGrave => 'Muy grave',
            self::Grave => 'Grave',
            self::Leve => 'Leve',
        };
    }
}
