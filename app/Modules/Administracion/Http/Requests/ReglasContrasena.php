<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Validation\Rules\Password;

/**
 * La regla de la contrasena propia, la misma en el primer ingreso y en el
 * restablecimiento: 10 caracteres o mas, mayuscula, minuscula y numero.
 * El cliente muestra las mismas reglas mientras se escribe.
 */
final class ReglasContrasena
{
    /**
     * @return list<Password>
     */
    public static function reglas(): array
    {
        return [Password::min(10)->mixedCase()->numbers()];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        // Las reglas de Password no tienen texto en lang/es.
        return [
            'password.password.mixed' => 'El campo contraseña debe tener mayúscula y minúscula.',
            'password.password.numbers' => 'El campo contraseña debe tener al menos un número.',
        ];
    }
}
