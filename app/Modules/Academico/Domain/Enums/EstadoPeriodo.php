<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Enums;

/**
 * El estado de un periodo no se guarda: se calcula con sus fechas y el dia
 * de hoy. Pueden estar vigentes varios a la vez.
 */
enum EstadoPeriodo: string
{
    case Vigente = 'VIGENTE';
    case Cerrado = 'CERRADO';
    case Proximo = 'PROXIMO';
    case SinFechas = 'SIN_FECHAS';

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
            self::Vigente => 'Vigente',
            self::Cerrado => 'Cerrado',
            self::Proximo => 'Próximo',
            self::SinFechas => 'Sin fechas',
        };
    }
}
