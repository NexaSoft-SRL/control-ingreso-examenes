<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * De que aula a cual se mueve, y a quien (sin `usuario_id`, a los
 * estudiantes que todavia no entraron).
 */
final class MoverEnElExamenRequest extends FormRequest
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
            'de_aula_id' => ['required', 'integer', 'min:1'],
            'a_aula_id' => ['required', 'integer', 'min:1'],
            'usuario_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'de_aula_id.*' => 'Elija el aula de origen.',
            'a_aula_id.*' => 'Elija el aula de destino.',
            'usuario_id.*' => 'La persona no es válida.',
        ];
    }
}
