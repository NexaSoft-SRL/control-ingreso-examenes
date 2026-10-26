<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SolicitarRestablecimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'usuario' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9._-]+$/'],
        ];
    }

    /**
     * Un correo u otro texto con espacios no es un usuario.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario.regex' => 'Usuario no válido.',
            'usuario.max' => 'Usuario no válido.',
        ];
    }
}
