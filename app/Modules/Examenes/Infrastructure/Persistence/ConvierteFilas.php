<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use LogicException;

/**
 * Las filas de `DB::table` llegan sin tipo: cada columna se convierte al
 * leerla.
 */
trait ConvierteFilas
{
    /**
     * @return array<string, mixed>
     */
    protected function columnas(object $fila): array
    {
        $columnas = [];

        foreach (get_object_vars($fila) as $nombre => $valor) {
            $columnas[(string) $nombre] = $valor;
        }

        return $columnas;
    }

    protected function entero(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor) && preg_match('/^-?\d+$/', $valor) === 1) {
            return (int) $valor;
        }

        throw new LogicException('La columna no contiene un entero válido.');
    }

    protected function enteroONulo(mixed $valor): ?int
    {
        return $valor === null ? null : $this->entero($valor);
    }

    protected function texto(mixed $valor): ?string
    {
        if (is_string($valor)) {
            return $valor;
        }

        return is_int($valor) ? (string) $valor : null;
    }

    protected function cadena(mixed $valor): string
    {
        return $this->texto($valor) ?? '';
    }

    protected function booleano(mixed $valor): bool
    {
        return $valor === true || $valor === 1 || $valor === '1' || $valor === 't';
    }

    /**
     * `08:15:00` -> `08:15`.
     */
    protected function hora(mixed $valor): string
    {
        return substr($this->cadena($valor), 0, 5);
    }

    /**
     * `2026-10-12 00:00:00` -> `2026-10-12`.
     */
    protected function fecha(mixed $valor): string
    {
        return substr($this->cadena($valor), 0, 10);
    }
}
