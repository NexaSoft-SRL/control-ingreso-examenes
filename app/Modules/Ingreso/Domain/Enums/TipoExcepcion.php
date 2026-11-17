<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Las situaciones excepcionales que se resuelven en la puerta. Todas quedan
 * como incidencia. Admiten al estudiante el cambio de aula y la admision
 * excepcional, que deja entrar a un no habilitado y solo la decide un
 * docente del examen.
 */
enum TipoExcepcion: string
{
    case ProblemaIdentificacion = 'PROBLEMA_IDENTIFICACION';
    case CambioAula = 'CAMBIO_AULA';
    case NoHabilitado = 'NO_HABILITADO';
    case AdmisionExcepcional = 'ADMISION_EXCEPCIONAL';

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
            self::ProblemaIdentificacion => 'Problema de identificación',
            self::CambioAula => 'Cambio de aula',
            self::NoHabilitado => 'Estudiante no habilitado',
            self::AdmisionExcepcional => 'Admitir por decisión del docente',
        };
    }

    public function admite(): bool
    {
        return $this === self::CambioAula || $this === self::AdmisionExcepcional;
    }

    /**
     * Si deja entrar aunque el estudiante no este habilitado.
     */
    public function pasaPorAltoLaHabilitacion(): bool
    {
        return $this === self::AdmisionExcepcional;
    }

    /**
     * Solo un docente del examen la decide; ni el auxiliar ni el codigo de
     * aula.
     */
    public function exigeDocente(): bool
    {
        return $this === self::AdmisionExcepcional;
    }

    /**
     * @return list<string>
     */
    public static function queAdmiten(): array
    {
        return array_values(array_map(
            static fn (self $tipo): string => $tipo->value,
            array_filter(self::cases(), static fn (self $tipo): bool => $tipo->admite()),
        ));
    }

    public function tipoIncidencia(): TipoIncidencia
    {
        return match ($this) {
            self::ProblemaIdentificacion => TipoIncidencia::Otra,
            self::CambioAula => TipoIncidencia::CambioAula,
            self::NoHabilitado => TipoIncidencia::IntentoNoAutorizado,
            self::AdmisionExcepcional => TipoIncidencia::AdmisionExcepcional,
        };
    }
}
