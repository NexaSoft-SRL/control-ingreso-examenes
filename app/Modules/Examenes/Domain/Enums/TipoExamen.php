<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Enums;

/**
 * Tipos de examen que reconoce la universidad. Se guardan como texto para
 * que el listado sea legible sin cruzar ninguna tabla.
 */
enum TipoExamen: string
{
    case PrimerParcial = 'PRIMER_PARCIAL';
    case SegundoParcial = 'SEGUNDO_PARCIAL';
    case Final = 'FINAL';
    case SegundaInstancia = 'SEGUNDA_INSTANCIA';
    case Mesa = 'MESA';

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
            self::PrimerParcial => 'Primer parcial',
            self::SegundoParcial => 'Segundo parcial',
            self::Final => 'Examen final',
            self::SegundaInstancia => 'Segunda instancia',
            self::Mesa => 'Examen de mesa',
        };
    }
}
