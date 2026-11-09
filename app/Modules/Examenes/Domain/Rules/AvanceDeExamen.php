<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Rules;

use App\Modules\Examenes\Domain\Enums\TipoExamen;

/**
 * Los cinco pasos de un examen (grupos, aulas, quien atiende, habilitacion
 * y codigos QR), su estado y el paso que sigue.
 */
final class AvanceDeExamen
{
    public const AULAS_POR_PERSONA = 2;

    public const OK = 'ok';

    public const AHORA = 'ahora';

    public const FALTA = 'falta';

    /**
     * @return array{
     *     grupos: string,
     *     aulas: string,
     *     auxiliares: string,
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
        int $conCodigoQr,
        int $aulasPorPersona = 1,
        int $sinAula = 0,
    ): array {
        $hayAulas = $aulas > 0;
        $habilitado = $sinRevisar === 0 && $habilitados > 0;
        // Habilitado y con aula: la credencial y la puerta la necesitan.
        $repartido = $habilitado && $sinAula === 0;
        $atendido = $aulasPorPersona <= self::AULAS_POR_PERSONA;
        $conCodigos = $repartido && $conCodigoQr >= $habilitados;

        [$estado, $accion, $paso] = match (true) {
            ! $hayAulas => ['Faltan aulas', 'elegir_aulas', 2],
            ! $atendido => ['Falta quién atiende', 'quien_atiende', 3],
            ! $habilitado => ['Falta habilitar', 'habilitar', null],
            ! $repartido => ['Falta repartir', 'repartir', null],
            ! $conCodigos => ['Faltan códigos QR', 'emitir_qr', null],
            default => ['Listo', 'ver_qr', null],
        };

        return [
            'grupos' => self::OK,
            'aulas' => $hayAulas ? self::OK : self::AHORA,
            // Una misma persona no atiende mas de dos aulas a la vez: con mas,
            // el paso queda pendiente aunque haya un encargado en cada una.
            'auxiliares' => match (true) {
                ! $hayAulas => self::FALTA,
                ! $atendido => self::AHORA,
                default => self::OK,
            },
            // Sin aulas, ni la habilitacion ni los codigos estan completos:
            // el habilitado todavia no tiene donde rendir.
            'habilitacion' => match (true) {
                $repartido && $hayAulas => self::OK,
                $hayAulas => self::AHORA,
                default => self::FALTA,
            },
            'qr' => match (true) {
                $conCodigos && $hayAulas => self::OK,
                $repartido && $hayAulas => self::AHORA,
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
