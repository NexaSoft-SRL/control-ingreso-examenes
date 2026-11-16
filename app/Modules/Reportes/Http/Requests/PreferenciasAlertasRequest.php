<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PreferenciasAlertasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asistencia_minima' => ['required', 'integer', 'between:0,100'],
            'incidencias_minimas' => ['required', 'integer', 'between:1,999'],
        ];
    }

    public function asistenciaMinima(): int
    {
        return (int) $this->validated('asistencia_minima');
    }

    public function incidenciasMinimas(): int
    {
        return (int) $this->validated('incidencias_minimas');
    }
}
