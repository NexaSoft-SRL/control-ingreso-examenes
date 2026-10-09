<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Infrastructure\Persistence;

use LogicException;

/**
 * Las filas de `DB::table` llegan sin tipo: cada columna se convierte aqui.
 */
trait ConvierteColumnas
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
        if (is_bool($valor)) {
            return $valor;
        }

        return in_array($valor, [1, '1', 't', 'true'], true);
    }
}
