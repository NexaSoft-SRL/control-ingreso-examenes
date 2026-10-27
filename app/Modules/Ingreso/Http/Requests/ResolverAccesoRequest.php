<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Al autorizar o trasladar un acceso con codigo, el aula donde va a
 * controlar (al autorizar es opcional: sin ella, la del codigo).
 */
final class ResolverAccesoRequest extends FormRequest
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
            'aula_id' => [$this->route('accion') === 'trasladar' ? 'required' : 'sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['aula_id.*' => 'Elija el aula.'];
    }
}
