<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SuspenderExamenRequest extends FormRequest
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
            'motivo' => [
                'required',
                'string',
                'max:100',
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function motivo(): string
    {
        return $this->string('motivo')->toString();
    }

    public function descripcion(): ?string
    {
        $valor = trim($this->string('descripcion')->toString());

        return $valor === ''
            ? null
            : $valor;
    }
}
