<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
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
        $studentId = (int) $this->route('student');

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'ci' => [
                'required',
                'string',
                'max:14',
                'regex:/^\d{5,10}(?:-[A-Z]{2,3})?$/',
                Rule::unique('students', 'ci')->ignore($studentId),
            ],
            'correo' => [
                'required',
                'email',
                'max:150',
                Rule::unique('students', 'correo')->ignore($studentId),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ci.regex' => 'Formato de C.I. inválido. Usa 5 a 10 dígitos y una extensión opcional (CB, LP, SC, etc.).'];
    }
}
