<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use App\Modules\Estudiantes\Domain\Enums\AlcanceCarga;
use Illuminate\Foundation\Http\FormRequest;

final class PlantillaRequest extends FormRequest
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
            'alcance' => ['sometimes', 'string', 'in:grupo,facultad'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alcance.string' => 'El alcance debe ser grupo o facultad.',
            'alcance.in' => 'El alcance debe ser grupo o facultad.',
        ];
    }

    public function alcance(): AlcanceCarga
    {
        return $this->validated('alcance') === 'facultad'
            ? AlcanceCarga::Facultad
            : AlcanceCarga::Grupo;
    }
}
