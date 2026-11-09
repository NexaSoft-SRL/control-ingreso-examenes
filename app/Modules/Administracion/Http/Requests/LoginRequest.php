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
            ],
            'password' => [
                'required',
                'string',
            ],
        ];
    }
}
