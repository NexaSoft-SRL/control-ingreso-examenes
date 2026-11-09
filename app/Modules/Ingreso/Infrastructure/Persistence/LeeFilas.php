<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;

/**
 * Ayudas comunes de los gateways del modulo: conversion de columnas, la
 * hora del servidor y la busqueda sin tildes.
 *
 * Todas las horas se expresan en la zona de la aplicacion
 * (`config('app.timezone')`), la misma en la que se guardan la fecha y la
 * hora de inicio de un examen.
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

    private function booleano(mixed $valor): bool
    {
        return $valor === true || $valor === 1 || $valor === '1' || $valor === 't';
    }

    /**
     * El instante actual como lo guarda la base: `AAAA-MM-DD HH:MM:SS`.
     */
    private function ahora(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function minutosDeApertura(): int
    {
        $minutos = config('umss.minutos_apertura', 60);

        return is_int($minutos) ? $minutos : 60;
    }

    /**
     * Pasa un instante de la base a ISO 8601 con zona.
     */
    private function iso(?string $instante): ?string
    {
        if ($instante === null) {
            return null;
        }

        $zona = config('app.timezone');

        return (new DateTimeImmutable(
            substr($instante, 0, 19),
            new DateTimeZone(is_string($zona) ? $zona : 'UTC'),
        ))->format('Y-m-d\TH:i:sP');
    }

    /**
     * Pasa un instante ISO 8601 a como lo guarda la base, en la zona de
     * la aplicacion.
     */
    private function deIso(string $instante): string
    {
        $zona = config('app.timezone');

        return (new DateTimeImmutable($instante))
            ->setTimezone(new DateTimeZone(is_string($zona) ? $zona : 'UTC'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Regla de la universidad: el correo institucional de un estudiante
     * es su codigo universitario en este dominio.
     */
    private function correoInstitucional(string $codigo): string
    {
        $dominio = config('umss.dominio_correo_estudiantes');

        return $codigo.'@'.(is_string($dominio) && $dominio !== '' ? $dominio : 'est.umss.edu');
    }

    /**
     * De `AAAA-MM-DD HH:MM:SS` toma `HH:MM:SS` (o `HH:MM`).
     */
    private function hora(?string $instante, bool $conSegundos = true): ?string
    {
        if ($instante === null) {
            return null;
        }

        return substr($instante, 11, $conSegundos ? 8 : 5);
    }

    private function nombreDe(mixed $apellidos, mixed $nombres): string
    {
        return trim(($this->texto($apellidos) ?? '').' '.($this->texto($nombres) ?? ''));
    }

    /**
     * La expresion SQL que compara sin mayusculas ni tildes.
     */
    private function sinTildes(string $expresion): string
    {
        return "translate(lower({$expresion}), 'áéíóúüñ', 'aeiouun')";
    }

    /**
     * El patron `ILIKE` de un texto de busqueda, o nulo si viene vacio.
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
