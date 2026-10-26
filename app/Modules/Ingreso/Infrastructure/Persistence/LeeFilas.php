<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;

/**
 * Conversion de las columnas de `DB::table`, que llegan sin tipo, y las
 * ayudas de busqueda de los codigos QR.
 */
trait LeeFilas
{
    /**
     * @return array<string, mixed>
     */
    private function columnas(object $fila): array
    {
        /** @var array<string, mixed> $columnas */
        $columnas = get_object_vars($fila);

        return $columnas;
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_string($valor)) {
            return $valor;
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        throw new LogicException('La columna no contiene un texto válido.');
    }

    private function entero(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor) && preg_match('/^-?\d+$/', $valor) === 1) {
            return (int) $valor;
        }

        throw new LogicException('La columna no contiene un entero válido.');
    }

    private function enteroONulo(mixed $valor): ?int
    {
        return $valor === null ? null : $this->entero($valor);
    }

    /**
     * Pasa un instante de la base a ISO 8601 con la zona de la aplicacion.
     */
    private function iso(?string $instante): ?string
    {
        if ($instante === null) {
            return null;
        }

        return (new DateTimeImmutable(substr($instante, 0, 19), $this->zona()))
            ->format('Y-m-d\TH:i:sP');
    }

    /**
     * Pasa un instante ISO 8601 a como lo guarda la base.
     */
    private function deIso(string $instante): string
    {
        return (new DateTimeImmutable($instante))
            ->setTimezone($this->zona())
            ->format('Y-m-d H:i:s');
    }

    private function zona(): DateTimeZone
    {
        $zona = config('app.timezone');

        return new DateTimeZone(is_string($zona) && $zona !== '' ? $zona : 'UTC');
    }

    /**
     * El correo institucional del estudiante: su codigo universitario en el
     * dominio de estudiantes de la universidad.
     */
    private function correoInstitucional(string $codigo): string
    {
        $dominio = config('umss.dominio_correo_estudiantes');

        return $codigo.'@'.(is_string($dominio) && $dominio !== '' ? $dominio : 'est.umss.edu');
    }

    /**
     * «Apellidos, Nombres», como en la habilitacion.
     */
    private function nombreDe(mixed $apellidos, mixed $nombres): string
    {
        return trim(($this->texto($apellidos) ?? '').', '.($this->texto($nombres) ?? ''), ', ');
    }

    /**
     * La expresion SQL que compara sin mayusculas ni tildes.
     */
    private function sinTildes(string $expresion): string
    {
        return "translate(lower({$expresion}), 'áéíóúüñ', 'aeiouun')";
    }

    /**
     * El patron `LIKE` de un texto de busqueda, o nulo si viene vacio.
     */
    private function patron(?string $buscar): ?string
    {
        $texto = trim((string) $buscar);

        if ($texto === '') {
            return null;
        }

        $texto = strtr(mb_strtolower($texto), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return '%'.addcslashes($texto, '%_\\').'%';
    }
}
