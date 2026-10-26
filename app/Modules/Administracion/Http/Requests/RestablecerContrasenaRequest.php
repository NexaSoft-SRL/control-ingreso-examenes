<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RestablecerContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'correo' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => [
                'required',
                'string',
                'confirmed',
                ...ReglasContrasena::reglas(),
            ],
        ];
    }

    /**
     * Un enlace alterado o recortado se informa igual que uno vencido.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $enlace = 'Enlace no válido o vencido.';

        return [
            'correo.*' => $enlace,
            'token.*' => $enlace,
            ...ReglasContrasena::mensajes(),
        ];
    }
}
