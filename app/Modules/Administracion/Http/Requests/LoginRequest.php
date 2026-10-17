<?php

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'usuario' => [
                'required',
                'string',
                'max:60',
            ],
            'password' => [
                'required',
                'string',
            ],
        ];
    }
}
