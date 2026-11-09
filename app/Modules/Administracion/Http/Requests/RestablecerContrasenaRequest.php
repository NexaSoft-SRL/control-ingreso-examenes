<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Domain\Rules\NombreDeUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RestablecerContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $usuario = $this->input('usuario');

        if (is_string($usuario)) {
            $this->merge(['usuario' => NombreDeUsuario::normalizar($usuario)]);
        }
    }

    /**
     * @return array<string, list<string|Password>>
     */
    public function rules(): array
    {
        return [
            'usuario' => [
                'required',
                'string',
                'max:60',
                'regex:'.NombreDeUsuario::FORMATO,
            ],
            'token' => [
                'required',
                'string',
            ],
            'contrasena_nueva' => [
                'required',
                'string',
                Password::min(10)->mixedCase()->numbers(),
                'confirmed',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario.required' => 'Falta el usuario del enlace.',
            'usuario.regex' => 'El usuario del enlace no es válido.',
            'token.required' => 'Falta el código del enlace.',
            'contrasena_nueva.required' => 'Escriba la contraseña nueva.',
            'contrasena_nueva.confirmed' => 'La confirmación no coincide con la contraseña nueva.',
            'contrasena_nueva.min' => 'La contraseña nueva debe tener al menos 10 caracteres.',
            'contrasena_nueva.password.mixed' => 'La contraseña nueva debe tener una mayúscula y una minúscula.',
            'contrasena_nueva.password.numbers' => 'La contraseña nueva debe tener un número.',
        ];
    }
}
