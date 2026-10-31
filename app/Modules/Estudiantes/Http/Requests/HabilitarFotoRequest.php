<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use App\Modules\Estudiantes\Domain\Rules\CambioDeFoto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Hasta que dia el estudiante puede subir o cambiar su foto: desde hoy
 * hasta un mes. Sin dia, una semana.
 */
final class HabilitarFotoRequest extends FormRequest
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
        $hoy = now()->toDateString();

        return [
            'hasta' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:'.$hoy,
                'before_or_equal:'.CambioDeFoto::ultimoDiaHabilitable($hoy),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hasta.date_format' => 'Fecha no válida',
            'hasta.after_or_equal' => 'No puede ser antes de hoy',
            'hasta.before_or_equal' => 'Máximo '.CambioDeFoto::DIAS_MAXIMOS.' días',
        ];
    }

    public function hasta(): ?string
    {
        return $this->filled('hasta') ? $this->string('hasta')->toString() : null;
    }
}
