<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class QuitarEstudianteAmbienteRequest extends FormRequest
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
            'estudiante_id' => ['required', 'integer', 'exists:students,id'],
        ];
    }

    /** @return array{ambiente_id: int, estudiante_id: int} */
    public function toData(): array
    {
        /** @var array{ambiente_id: int|string, estudiante_id: int|string} $data */
        $data = $this->validated();

        return [
            'ambiente_id' => (int) $data['ambiente_id'],
            'estudiante_id' => (int) $data['estudiante_id'],
        ];
    }
}
