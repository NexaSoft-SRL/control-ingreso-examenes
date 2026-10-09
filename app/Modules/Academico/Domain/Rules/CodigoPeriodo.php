<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Rules;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;

/**
 * El codigo de un periodo es `numero/año`: 0 anual, 1 y 2 semestres,
 * 3 verano, 4 invierno.
 */
final class CodigoPeriodo
{
    /**
     * @return array{numero: int, anio: int}|null
     */
    public static function partes(string $codigo): ?array
    {
        if (preg_match('/^(\d)\/(\d{4})$/', trim($codigo), $partes) !== 1) {
            return null;
        }

        return ['numero' => (int) $partes[1], 'anio' => (int) $partes[2]];
    }

    public static function tipo(int $numero): TipoPeriodo
    {
        return match ($numero) {
            0 => TipoPeriodo::Anual,
            1 => TipoPeriodo::Semestre1,
            3 => TipoPeriodo::Verano,
            4 => TipoPeriodo::Invierno,
            default => TipoPeriodo::Semestre2,
        };
    }

    /**
     * El periodo anual del mismo año: las carreras anuales tienen ahi sus
     * grupos, diga lo que diga el archivo.
     */
    public static function anualDe(int $anio): string
    {
        return '0/'.$anio;
    }
}
