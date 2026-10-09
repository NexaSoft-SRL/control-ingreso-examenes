<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AjustarPeriodoRequest extends FormRequest
{
    use ValidaEnEspanol;

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
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after:fecha_inicio'],
        ];
    }

    public function fechaInicio(): string
    {
        return $this->string('fecha_inicio')->toString();
    }

    public function fechaFin(): string
    {
        return $this->string('fecha_fin')->toString();
    }
}
