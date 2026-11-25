<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SumarAlEquipoRequest extends FormRequest
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
            'usuario_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario_id.required' => 'Elija a un auxiliar.',
            'usuario_id.integer' => 'Elija a un auxiliar.',
            'usuario_id.min' => 'Elija a un auxiliar.',
        ];
    }

    public function usuarioId(): int
    {
        return $this->integer('usuario_id');
    }
}
