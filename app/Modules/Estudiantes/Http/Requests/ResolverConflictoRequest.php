<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use App\Modules\Estudiantes\Domain\Enums\ResolucionConflicto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResolverConflictoRequest extends FormRequest
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
            'resolucion' => ['required', 'string', Rule::in(ResolucionConflicto::valores())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resolucion.required' => 'La resolución es obligatoria.',
            'resolucion.string' => 'La resolución no es válida.',
            'resolucion.in' => 'La resolución debe ser MANTENER_PADRON o USAR_CARGA.',
        ];
    }

    public function resolucion(): ResolucionConflicto
    {
        return ResolucionConflicto::from($this->string('resolucion')->toString());
    }
}
