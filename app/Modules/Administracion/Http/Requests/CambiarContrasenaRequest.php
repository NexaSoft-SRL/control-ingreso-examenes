<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $usuario = $this->user();
        // La cuenta con contrasena temporal no repite la temporal: entro con
        // ella. La cuenta con contrasena propia si debe darla.
        $esTemporal = $usuario instanceof User
            && $usuario->getAttribute('password_changed_at') === null;

        return [
            'contrasena_actual' => [Rule::requiredIf(! $esTemporal), 'nullable', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:contrasena_actual',
                ...ReglasContrasena::reglas(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.different' => 'La contraseña nueva debe ser distinta de la actual.',
            ...ReglasContrasena::mensajes(),
        ];
    }
}
