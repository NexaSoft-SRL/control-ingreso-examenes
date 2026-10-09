<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Rules;

use App\Modules\Examenes\Domain\Enums\TipoExamen;

/**
 * Los cuatro pasos de un examen (grupos, aulas, habilitacion y codigos QR),
 * su estado y el paso que sigue. Los codigos QR todavia no se emiten: ese
 * paso queda siempre pendiente y el estado maximo es «Faltan códigos QR».
 */
final class AvanceDeExamen
{
    public const OK = 'ok';

    public const AHORA = 'ahora';

    public const FALTA = 'falta';

    /**
     * @return array{
     *     grupos: string,
     *     aulas: string,
     *     habilitacion: string,
     *     qr: string,
     *     estado: string,
     *     accion: string,
     *     paso: int|null
     * }
     */
    public static function calcular(
        int $aulas,
        int $habilitados,
        int $sinRevisar,
    ): array {
        $hayAulas = $aulas > 0;
        $habilitado = $sinRevisar === 0 && $habilitados > 0;

        [$estado, $accion, $paso] = match (true) {
            ! $hayAulas => ['Faltan aulas', 'elegir_aulas', 2],
            ! $habilitado => ['Falta habilitar', 'habilitar', null],
            default => ['Faltan códigos QR', 'emitir_qr', null],
        };

        return [
            'grupos' => self::OK,
            'aulas' => $hayAulas ? self::OK : self::AHORA,
            'habilitacion' => match (true) {
                $habilitado => self::OK,
                $hayAulas => self::AHORA,
                default => self::FALTA,
            },
            'qr' => $habilitado ? self::AHORA : self::FALTA,
            'estado' => $estado,
            'accion' => $accion,
            'paso' => $paso,
        ];
    }

    /**
     * El tipo con su articulo, para los mensajes: «un primer parcial».
     */
    public static function tipoConArticulo(TipoExamen $tipo): string
    {
        return match ($tipo) {
            TipoExamen::PrimerParcial => 'un primer parcial',
            TipoExamen::SegundoParcial => 'un segundo parcial',
            TipoExamen::Final => 'un examen final',
            TipoExamen::SegundaInstancia => 'una segunda instancia',
            TipoExamen::Mesa => 'un examen de mesa',
        };
    }
}
