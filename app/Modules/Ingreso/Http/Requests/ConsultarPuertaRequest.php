<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConsultarPuertaRequest extends FormRequest
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
            'ingresos' => ['nullable', 'string', 'in:recientes,todos'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ingresos.*' => 'Los ingresos se piden como «recientes» o «todos».',
            'buscar.*' => 'La búsqueda admite hasta 100 caracteres.',
        ];
    }

    public function todos(): bool
    {
        return $this->validated('ingresos') === 'todos';
    }

    public function buscar(): ?string
    {
        $buscar = $this->validated('buscar');

        return is_string($buscar) ? $buscar : null;
    }
}
