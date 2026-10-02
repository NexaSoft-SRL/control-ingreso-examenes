<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use App\Modules\Examenes\Application\DTOs\RegistrarNormaExamenData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegistrarNormaExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alcance' => ['required', 'string', Rule::in(['general', 'particular'])],
            'texto' => ['required', 'string', 'max:5000'],
            'estudiante_id' => [
                'required_if:alcance,particular',
                'prohibited_unless:alcance,particular',
                'integer',
                Rule::exists('students', 'id'),
            ],
            'motivo' => [
                'required_if:alcance,particular',
                'prohibited_unless:alcance,particular',
                'string',
                'max:2000',
            ],
        ];
    }

    public function toData(): RegistrarNormaExamenData
    {
        /**
         * @var array{
         *     alcance: string,
         *     texto: string,
         *     estudiante_id?: int,
         *     motivo?: string
         * } $validated
         */
        $validated = $this->validated();

        return new RegistrarNormaExamenData(
            alcance: $validated['alcance'],
            texto: $validated['texto'],
            estudianteId: isset($validated['estudiante_id'])
                ? (int) $validated['estudiante_id']
                : null,
            motivo: $validated['motivo'] ?? null,
        );
    }
}
