<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Catalogo cerrado de incidencias que se registran desde la puerta
 * (`incidencias_aula.tipo`). Los codigos son los que ya guarda el sistema;
 * las pantallas muestran la etiqueta.
 */
enum TipoIncidenciaPuerta: string
{
    case IncidenteGeneral = 'INCIDENTE_GENERAL';
    case DocumentoObservado = 'DOCUMENTO_OBSERVADO';
    case Conducta = 'CONDUCTA';
    case Otro = 'OTRO';
    case ExcepcionIdentificacion = 'EXCEPCION_IDENTIFICACION';
    case ExcepcionCambioAula = 'EXCEPCION_CAMBIO_AULA';
    case ExcepcionNoHabilitado = 'EXCEPCION_NO_HABILITADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::IncidenteGeneral => 'Incidente general',
            self::DocumentoObservado => 'Documento observado',
            self::Conducta => 'Conducta',
            self::Otro => 'Otro',
            self::ExcepcionIdentificacion => 'Excepción: problema de identificación',
            self::ExcepcionCambioAula => 'Excepción: cambio de aula',
            self::ExcepcionNoHabilitado => 'Excepción: estudiante no habilitado',
        };
    }

    // Situacion excepcional (con motivo), no incidencia comun.
    public function esExcepcion(): bool
    {
        return str_starts_with($this->value, 'EXCEPCION_');
    }

    // Solo el cambio de aula deja entrar (como en el Proyecto Guia).
    public function admite(): bool
    {
        return $this === self::ExcepcionCambioAula;
    }

    /**
     * @return list<string>
     */
    public static function comunes(): array
    {
        return array_values(array_map(
            static fn (self $tipo): string => $tipo->value,
            array_filter(self::cases(), static fn (self $tipo): bool => ! $tipo->esExcepcion()),
        ));
    }

    /**
     * @return list<string>
     */
    public static function excepciones(): array
    {
        return array_values(array_map(
            static fn (self $tipo): string => $tipo->value,
            array_filter(self::cases(), static fn (self $tipo): bool => $tipo->esExcepcion()),
        ));
    }
}
