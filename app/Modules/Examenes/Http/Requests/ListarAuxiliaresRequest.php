<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListarAuxiliaresRequest extends FormRequest
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
            'buscar' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'buscar.string' => 'La búsqueda no es válida.',
            'buscar.max' => 'La búsqueda admite hasta 60 caracteres.',
        ];
    }

    public function buscar(): ?string
    {
        $buscar = $this->validated('buscar');

        return is_string($buscar) ? $buscar : null;
    }
}
