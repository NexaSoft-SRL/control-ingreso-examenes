<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Application\Actions\ResolverIrregularidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResolverIrregularidadRequest extends FormRequest
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
            'estado' => ['required', 'string', Rule::in(ResolverIrregularidad::ESTADOS)],
            'nota' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.*' => 'Elija confirmar o descartar.',
            'nota.required' => 'Mínimo 5 caracteres',
            'nota.min' => 'Mínimo 5 caracteres',
            'nota.max' => 'Máximo 1000 caracteres',
        ];
    }
}
