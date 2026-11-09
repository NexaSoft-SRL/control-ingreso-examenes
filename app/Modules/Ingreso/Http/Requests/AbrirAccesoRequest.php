<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AbrirAccesoRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'regex:/^\d{6}$/'],
            // Es la firma de lo que registre: tiene que ser un nombre.
            'nombre' => ['required', 'string', 'max:120', "regex:/^(?=(?:.*\\p{L}){3})\\p{L}+(?:(?:\\.\\s*|\\s+|['-])\\p{L}+)*\\.?$/u"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'Seis dígitos',
            'codigo.string' => 'Seis dígitos',
            'codigo.regex' => 'Seis dígitos',
            'nombre.required' => 'Obligatorio',
            'nombre.string' => 'Mínimo 3 caracteres',
            'nombre.min' => 'Mínimo 3 caracteres',
            'nombre.max' => 'Máximo 120 caracteres',
            'nombre.regex' => 'Mínimo 3 letras, sin signos seguidos',
        ];
    }
}
