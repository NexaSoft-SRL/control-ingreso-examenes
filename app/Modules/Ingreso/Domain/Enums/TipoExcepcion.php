<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Las situaciones excepcionales que se resuelven en la puerta. Todas quedan
 * como incidencia; solo el cambio de aula admite al estudiante.
 */
enum TipoExcepcion: string
{
    case Identificacion = 'EXCEPCION_IDENTIFICACION';
    case CambioAula = 'EXCEPCION_CAMBIO_AULA';
    case NoHabilitado = 'EXCEPCION_NO_HABILITADO';

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
            self::Identificacion => 'Problema de identificación',
            self::CambioAula => 'Cambio de aula',
            self::NoHabilitado => 'Estudiante no habilitado',
        };
    }

    public function admite(): bool
    {
        return $this === self::CambioAula;
    }

    public function tipoIncidencia(): TipoIncidencia
    {
        return match ($this) {
            self::Identificacion => TipoIncidencia::Otra,
            self::CambioAula => TipoIncidencia::CambioAula,
            self::NoHabilitado => TipoIncidencia::IntentoNoAutorizado,
        };
    }
}
