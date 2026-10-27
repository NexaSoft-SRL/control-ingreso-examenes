<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListarIncidenciasRequest extends FormRequest
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
            'aula' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'string', 'in:sin_atender,todas'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aula.*' => 'El aula no es válida.',
            'estado.*' => 'El estado es «sin_atender» o «todas».',
        ];
    }

    public function aulaId(): ?int
    {
        return $this->filled('aula') ? $this->integer('aula') : null;
    }

    public function soloSinAtender(): bool
    {
        return $this->validated('estado') === 'sin_atender';
    }
}
