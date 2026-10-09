<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListarExamenesRequest extends FormRequest
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
            // El id del periodo o su codigo (`2/2026`).
            'periodo' => ['nullable', 'string', 'max:10', 'regex:/^\d+(\/\d+)?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periodo.string' => 'El período no es válido.',
            'periodo.max' => 'El período no es válido.',
            'periodo.regex' => 'El período no es válido.',
        ];
    }

    public function periodo(): ?string
    {
        $periodo = $this->validated('periodo');

        return is_string($periodo) && $periodo !== '' ? $periodo : null;
    }
}
