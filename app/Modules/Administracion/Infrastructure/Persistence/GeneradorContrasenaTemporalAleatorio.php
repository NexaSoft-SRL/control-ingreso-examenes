<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\GeneradorContrasenaTemporal;

/**
 * Formato `Xx9-Xxx9-Xx9` (por ejemplo `Fa7-Kmq4-Ru9`). El alfabeto deja
 * fuera la i, la l y la o, y los digitos 0 y 1: se confunden al copiarlos
 * a mano.
 */
final class GeneradorContrasenaTemporalAleatorio implements GeneradorContrasenaTemporal
{
    private const MAYUSCULAS = 'ABCDEFGHJKMNPQRSTUVWXYZ';

    private const MINUSCULAS = 'abcdefghjkmnpqrstuvwxyz';

    private const DIGITOS = '23456789';

    private const FORMATO = 'Xx9-Xxx9-Xx9';

    public function generar(): string
    {
        $contrasena = '';

        foreach (str_split(self::FORMATO) as $marca) {
            $contrasena .= match ($marca) {
                'X' => $this->alAzar(self::MAYUSCULAS),
                'x' => $this->alAzar(self::MINUSCULAS),
                '9' => $this->alAzar(self::DIGITOS),
                default => $marca,
            };
        }

        return $contrasena;
    }

    private function alAzar(string $alfabeto): string
    {
        return $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
}
