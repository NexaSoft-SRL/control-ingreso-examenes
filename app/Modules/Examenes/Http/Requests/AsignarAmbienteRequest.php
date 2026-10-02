<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AsignarAmbienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $examenId = (int) $this->route('examen');

        return [
            'ambiente_id' => [
                'required',
                'integer',
                Rule::exists('ambientes', 'id'),
                Rule::unique('examen_ambiente', 'ambiente_id')
                    ->where('examen_id', $examenId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ambiente_id.unique' => 'Ese ambiente ya está asignado al examen.',
        ];
    }
}
