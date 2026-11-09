<?php

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Domain\Rules\NombreDeUsuario;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El usuario va en minusculas, sin tildes y con un espacio entre nombre
     * y apellido: «Leticia  Blanco» es «leticia blanco».
     */
    protected function prepareForValidation(): void
    {
        $usuario = $this->input('usuario');

        if (is_string($usuario)) {
            $this->merge(['usuario' => NombreDeUsuario::normalizar($usuario)]);
        }
    }

    /**
     * @return array<string, array<int, string>>
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
            'password' => [
                'required',
                'string',
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
            'password.required' => 'Escriba su contraseña.',
        ];
    }
}
