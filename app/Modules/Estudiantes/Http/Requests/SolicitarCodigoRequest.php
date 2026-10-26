<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SolicitarCodigoRequest extends FormRequest
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
            'codigo_universitario' => ['required', 'string', 'regex:/^\d{5,10}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_universitario.*' => 'Entre 5 y 10 dígitos',
        ];
    }

    public function codigoUniversitario(): string
    {
        return $this->string('codigo_universitario')->trim()->toString();
    }
}
