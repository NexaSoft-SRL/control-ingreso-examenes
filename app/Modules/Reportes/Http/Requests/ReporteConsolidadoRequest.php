<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReporteConsolidadoRequest extends FormRequest
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
            'examenes' => ['required', 'array', 'min:2', 'max:20'],
            'examenes.*' => ['required', 'integer', 'min:1', 'distinct', Rule::exists('examenes', 'id')],
            'formato' => ['nullable', Rule::in(['pdf', 'xlsx'])],
        ];
    }

    /**
     * @return list<int>
     */
    public function examenIds(): array
    {
        $ids = $this->validated('examenes');

        return array_map(static fn (string|int $id): int => (int) $id, $ids);
    }

    public function formato(): string
    {
        return $this->validated('formato') === 'xlsx' ? 'xlsx' : 'pdf';
    }
}
