<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Domain\Rules\NombreDeUsuario;
use Illuminate\Foundation\Http\FormRequest;

final class SolicitarRestablecimientoRequest extends FormRequest
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
     * @return array<string, list<string>>
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario.required' => 'Escriba su usuario.',
            'usuario.max' => 'El usuario no puede pasar de 60 caracteres.',
            'usuario.regex' => 'El usuario lleva letras y números, separados por un espacio, punto o guion.',
        ];
    }
}
