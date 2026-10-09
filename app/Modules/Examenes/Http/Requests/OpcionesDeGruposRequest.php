<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OpcionesDeGruposRequest extends FormRequest
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
            'asignatura_id' => ['required', 'integer', 'exists:asignaturas,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asignatura_id.required' => 'Elige la asignatura.',
            'asignatura_id.integer' => 'La asignatura no es válida.',
            'asignatura_id.exists' => 'La asignatura no existe.',
        ];
    }
}
