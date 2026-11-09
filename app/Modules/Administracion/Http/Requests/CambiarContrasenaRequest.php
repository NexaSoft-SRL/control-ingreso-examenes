<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class CambiarContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `contrasena_actual` no es obligatoria aqui: con contrasena temporal
     * no se pide. Si hace falta, lo decide la accion.
     *
     * @return array<string, list<string|Password>>
     */
    public function rules(): array
    {
        return [
            'contrasena_actual' => [
                'nullable',
                'string',
            ],
            'contrasena_nueva' => [
                'required',
                'string',
                Password::min(10)->mixedCase()->numbers(),
                'confirmed',
                'different:contrasena_actual',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contrasena_nueva.required' => 'Escriba la contraseña nueva.',
            'contrasena_nueva.different' => 'La contraseña nueva debe ser distinta de la actual.',
            'contrasena_nueva.confirmed' => 'La confirmación no coincide con la contraseña nueva.',
            'contrasena_nueva.min' => 'La contraseña nueva debe tener al menos 10 caracteres.',
            'contrasena_nueva.password.mixed' => 'La contraseña nueva debe tener una mayúscula y una minúscula.',
            'contrasena_nueva.password.numbers' => 'La contraseña nueva debe tener un número.',
        ];
    }
}
