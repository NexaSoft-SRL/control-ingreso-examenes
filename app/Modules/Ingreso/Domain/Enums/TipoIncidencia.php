<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Todo lo que queda asentado en un examen ademas de los ingresos: lo que
 * reporta una persona, lo que rechaza la puerta, las expulsiones y las
 * situaciones excepcionales.
 */
enum TipoIncidencia: string
{
    case IntentoNoAutorizado = 'INTENTO_NO_AUTORIZADO';
    case Suplantacion = 'SUPLANTACION';
    case MaterialNoPermitido = 'MATERIAL_NO_PERMITIDO';
    // Reloj inteligente, celular, audifonos.
    case DispositivoNoPermitido = 'DISPOSITIVO_NO_PERMITIDO';
    // La puerta lo asienta sola: una identificacion que ya entro (un QR
    // prestado, alguien que se hace pasar por otro).
    case IngresoRepetido = 'INGRESO_REPETIDO';
    // Exige un ingreso previo y se asienta una sola vez por ingreso.
    case Expulsion = 'EXPULSION';
    case CambioAula = 'CAMBIO_AULA';
    case Otra = 'OTRA';

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
            self::IntentoNoAutorizado => 'Intento rechazado',
            self::Suplantacion => 'Suplantación de identidad',
            self::MaterialNoPermitido => 'Material no permitido',
            self::DispositivoNoPermitido => 'Dispositivo no permitido',
            self::IngresoRepetido => 'Ingreso repetido',
            self::Expulsion => 'Expulsión',
            self::CambioAula => 'Cambio de aula',
            self::Otra => 'Otra',
        };
    }

    public function gravedad(): Gravedad
    {
        return match ($this) {
            self::Suplantacion, self::IngresoRepetido, self::Expulsion => Gravedad::Grave,
            self::IntentoNoAutorizado, self::MaterialNoPermitido, self::DispositivoNoPermitido => Gravedad::Media,
            self::CambioAula, self::Otra => Gravedad::Leve,
        };
    }

    /**
     * Los tipos que una persona puede elegir al reportar. La expulsion
     * tiene su propia operacion porque exige el ingreso, y el ingreso
     * repetido lo asienta la puerta.
     *
     * @return list<string>
     */
    public static function reportables(): array
    {
        return array_values(array_filter(
            self::valores(),
            static fn (string $valor): bool => ! in_array($valor, [self::Expulsion->value, self::IngresoRepetido->value], true),
        ));
    }
}
