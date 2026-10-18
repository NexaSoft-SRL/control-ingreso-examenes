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
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:contrasena_actual',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }
}
