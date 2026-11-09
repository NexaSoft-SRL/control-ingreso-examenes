<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Un estudiante suelto que el docente agrega a la lista de su grupo. Los
 * formatos (codigo SIS, C.I., nombres) los revisa la carga, igual que en una
 * fila del archivo; aqui solo que vengan.
 */
final class AgregarInscritoRequest extends FormRequest
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
            'codigo_universitario' => ['required', 'string', 'max:20'],
            'documento_identidad' => ['required', 'string', 'max:20'],
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.required' => 'Obligatorio',
            '*.string' => 'Obligatorio',
            '*.max' => 'Demasiado largo',
        ];
    }

    /**
     * La fila en el orden de la lista de un grupo.
     *
     * @return list<string>
     */
    public function fila(): array
    {
        return [
            $this->string('codigo_universitario')->trim()->toString(),
            $this->string('documento_identidad')->trim()->toString(),
            $this->string('nombres')->trim()->toString(),
            $this->string('apellidos')->trim()->toString(),
        ];
    }
}
