<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegistrarAuxiliarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');
        $codigo = $this->input('codigo_universitario');

        $this->merge([
            'nombre' => is_string($nombre) ? trim((string) preg_replace('/\s+/u', ' ', $nombre)) : $nombre,
            'codigo_universitario' => is_string($codigo) ? trim($codigo) : $codigo,
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:120', "regex:/^(?=(?:.*\\p{L}){2})\\p{L}+(?:(?:\\.\\s*|\\s+|['-])\\p{L}+)*\\.?$/u"],
            'codigo_universitario' => ['required', 'string', 'regex:/^\d{5,10}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Obligatorio',
            'nombre.string' => 'No válido',
            'nombre.min' => 'Entre 3 y 120 caracteres',
            'nombre.max' => 'Entre 3 y 120 caracteres',
            'nombre.regex' => 'Solo letras, sin signos seguidos',
            'codigo_universitario.required' => 'Obligatorio',
            'codigo_universitario.string' => 'Entre 5 y 10 dígitos',
            'codigo_universitario.regex' => 'Entre 5 y 10 dígitos',
        ];
    }

    public function nombre(): string
    {
        return $this->string('nombre')->toString();
    }

    public function codigoUniversitario(): string
    {
        return $this->string('codigo_universitario')->toString();
    }
}
