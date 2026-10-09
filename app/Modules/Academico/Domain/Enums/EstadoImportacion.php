<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Enums;

/**
 * Como termino una importacion de la oferta de una facultad. Sin filas, la
 * facultad esta «Sin importar».
 */
enum EstadoImportacion: string
{
    case Importando = 'IMPORTANDO';
    case Importada = 'IMPORTADA';
    case Fallo = 'FALLO';

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
            self::Importando => 'Importando',
            self::Importada => 'Importada',
            self::Fallo => 'Falló',
        };
    }
}
