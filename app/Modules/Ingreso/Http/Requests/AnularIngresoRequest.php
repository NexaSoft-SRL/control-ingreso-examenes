<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AnularIngresoRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Obligatorio',
            'motivo.string' => 'Obligatorio',
            'motivo.min' => 'Mínimo 3 caracteres',
            'motivo.max' => 'Máximo 500 caracteres',
        ];
    }

    public function motivo(): string
    {
        return trim($this->string('motivo')->toString());
    }
}
