<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AsignarEstudiantesAmbienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ambiente_id' => ['required', 'integer', 'exists:ambientes,id'],
            'estudiante_ids' => ['required', 'array', 'min:1'],
            'estudiante_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ];
    }

    /** @return array{ambiente_id: int, estudiante_ids: list<int>} */
    public function toData(): array
    {
        /** @var array{ambiente_id: int|string, estudiante_ids: list<int|string>} $data */
        $data = $this->validated();

        return [
            'ambiente_id' => (int) $data['ambiente_id'],
            'estudiante_ids' => array_map(
                static fn (int|string $id): int => (int) $id,
                $data['estudiante_ids'],
            ),
        ];
    }
}
