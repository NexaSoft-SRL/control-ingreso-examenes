<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Hasta que dia suben su foto los inscritos del grupo: desde hoy y no mas
 * alla de un semestre. Nulo quita el plazo.
 */
final class PlazoFotoDeGrupoRequest extends FormRequest
{
    public const DIAS_MAXIMOS = 180;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $hoy = now();

        return [
            'hasta' => [
                'present',
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:'.$hoy->toDateString(),
                'before_or_equal:'.$hoy->copy()->addDays(self::DIAS_MAXIMOS)->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hasta.present' => 'Obligatorio',
            'hasta.date_format' => 'Fecha no válida',
            'hasta.after_or_equal' => 'No puede ser antes de hoy',
            'hasta.before_or_equal' => 'Máximo '.self::DIAS_MAXIMOS.' días',
        ];
    }

    public function hasta(): ?string
    {
        return $this->filled('hasta') ? $this->string('hasta')->toString() : null;
    }
}
