<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompararReportesRequest extends FormRequest
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
            'examenes' => ['nullable', 'array', 'min:2', 'max:20', 'required_without:periodos'],
            'examenes.*' => ['required', 'integer', 'min:1', 'distinct', Rule::exists('examenes', 'id')],
            'periodos' => ['nullable', 'array', 'min:2', 'max:10', 'required_without:examenes'],
            'periodos.*' => ['required', 'integer', 'min:1', 'distinct', Rule::exists('periodos', 'id')],
        ];
    }

    /**
     * @return list<int>
     */
    public function examenIds(): array
    {
        return $this->ids('examenes');
    }

    /**
     * @return list<int>
     */
    public function periodoIds(): array
    {
        return $this->ids('periodos');
    }

    /**
     * @return list<int>
     */
    private function ids(string $campo): array
    {
        $valores = $this->validated($campo, []);

        return is_array($valores)
            ? array_map(static fn (string|int $id): int => (int) $id, $valores)
            : [];
    }
}
