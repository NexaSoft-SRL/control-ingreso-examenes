<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El carnet que presenta quien llega a la puerta, para compararlo con el
 * del estudiante que se identifico.
 */
final class CarnetEnPuertaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'estudiante_id' => ['required', 'integer', 'min:1'],
            'examen_id' => ['required', 'integer', 'min:1'],
            'documento' => ['required', 'string', 'regex:/^\d{4,10}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estudiante_id.*' => 'Falta el estudiante.',
            'examen_id.*' => 'Falta el examen.',
            'documento.required' => 'Escriba el carnet',
            'documento.regex' => 'Solo números, de 4 a 10 dígitos',
        ];
    }
}
