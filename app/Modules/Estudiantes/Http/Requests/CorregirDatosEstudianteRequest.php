<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CorregirDatosEstudianteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombres' => is_string($this->input('nombres')) ? trim($this->input('nombres')) : $this->input('nombres'),
            'apellidos' => is_string($this->input('apellidos')) ? trim($this->input('apellidos')) : $this->input('apellidos'),
            'documento' => is_string($this->input('documento')) ? trim($this->input('documento')) : $this->input('documento'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'documento' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('estudiantes', 'documento_identidad')->ignore($this->route('estudiante')),
            ],
        ];
    }
}
