<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegistrarCondicionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'estudiante_ids' => ['required', 'array', 'min:1'],
            'estudiante_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('students', 'id')->where('activo', true),
            ],
            'condicion' => ['required', 'in:HABILITADO,NO_HABILITADO'],
            'motivo' => ['nullable', 'required_if:condicion,NO_HABILITADO', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'estudiante_ids.required' => 'Selecciona al menos un estudiante.',
            'estudiante_ids.*.exists' => 'El estudiante indicado debe existir y estar activo en el padrón.',
            'condicion.in' => 'La condición debe ser HABILITADO o NO_HABILITADO.',
            'motivo.required_if' => 'El motivo es obligatorio para inhabilitar.',
        ];
    }

    /** @return array{estudiante_ids: list<int>, condicion: string, motivo: ?string} */
    public function toData(): array
    {
        /** @var array{estudiante_ids: list<int|string>, condicion: string, motivo?: string|null} $data */
        $data = $this->validated();

        return [
            'estudiante_ids' => array_map(static fn (int|string $id): int => (int) $id, $data['estudiante_ids']),
            'condicion' => $data['condicion'],
            'motivo' => isset($data['motivo']) ? trim($data['motivo']) : null,
        ];
    }
}
