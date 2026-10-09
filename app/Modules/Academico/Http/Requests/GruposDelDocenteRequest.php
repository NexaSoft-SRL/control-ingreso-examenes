<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GruposDelDocenteRequest extends FormRequest
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
            'periodo' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function periodoId(): ?int
    {
        return $this->enteroOpcional('periodo');
    }
}
