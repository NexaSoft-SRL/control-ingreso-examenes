<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Lo que responde la puerta al verificar a un estudiante. Va en minusculas
 * porque viaja tal cual en la respuesta.
 */
enum Veredicto: string
{
    case Admitido = 'admitido';
    case NoHabilitado = 'no_habilitado';
    case OtraAula = 'otra_aula';
    case YaIngreso = 'ya_ingreso';
    case Expulsado = 'expulsado';
    case NoCorresponde = 'no_corresponde';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $veredicto): string => $veredicto->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admitido => 'Admitido',
            self::NoHabilitado => 'No habilitado',
            self::OtraAula => 'Otra aula',
            self::YaIngreso => 'Ya ingresó',
            self::Expulsado => 'Expulsado',
            self::NoCorresponde => 'No es de esta aula',
        };
    }
}
