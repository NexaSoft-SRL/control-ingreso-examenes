<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListarReportesRequest extends FormRequest
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
            'periodo' => ['nullable', 'integer', 'min:1'],
            'asignatura' => ['nullable', 'integer', 'min:1'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
