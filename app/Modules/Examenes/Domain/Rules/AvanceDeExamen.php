<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Rules;

use App\Modules\Examenes\Domain\Enums\TipoExamen;

/**
 * Los cuatro pasos de un examen (grupos, aulas, habilitacion y codigos QR),
 * su estado y el paso que sigue. Esta «Listo» cuando cada habilitado tiene
 * su codigo QR (HU-20).
 */
final class AvanceDeExamen
{
    // Una misma persona no atiende mas de dos aulas a la vez.
    public const AULAS_POR_PERSONA = 2;

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
        int $conCodigoQr = 0,
        int $aulasPorPersona = 1,
    ): array {
        $hayAulas = $aulas > 0;
        $atendido = $aulasPorPersona <= self::AULAS_POR_PERSONA;
        $habilitado = $sinRevisar === 0 && $habilitados > 0;
        $conCodigos = $habilitado && $conCodigoQr >= $habilitados;

        [$estado, $accion, $paso] = match (true) {
            ! $hayAulas => ['Faltan aulas', 'elegir_aulas', 2],
            // Con alguien a cargo de mas de dos aulas, el paso de quien
            // atiende queda pendiente aunque cada aula tenga encargado.
            ! $atendido => ['Falta quién atiende', 'quien_atiende', 3],
            ! $habilitado => ['Falta habilitar', 'habilitar', null],
            ! $conCodigos => ['Faltan códigos QR', 'emitir_qr', null],
            default => ['Listo', 'ver_qr', null],
        };

        return [
            'grupos' => self::OK,
            'aulas' => $hayAulas ? self::OK : self::AHORA,
            'habilitacion' => match (true) {
                $habilitado => self::OK,
                $hayAulas => self::AHORA,
                default => self::FALTA,
            },
            'qr' => match (true) {
                $conCodigos && $hayAulas => self::OK,
                $habilitado && $hayAulas => self::AHORA,
                default => self::FALTA,
            },
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
