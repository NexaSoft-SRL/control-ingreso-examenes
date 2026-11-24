<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use App\Modules\Estudiantes\Domain\Rules\DatosDeInscrito;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los datos con que se empadrona el estudiante: las mismas reglas que una
 * fila del archivo de carga, mas la carrera.
 */
final class EmpadronarseRequest extends FormRequest
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
            'documento' => ['required', 'string', 'max:'.DatosDeInscrito::MAXIMO_DOCUMENTO, 'regex:'.DatosDeInscrito::PATRON_DOCUMENTO],
            'nombres' => ['required', 'string', 'max:'.DatosDeInscrito::MAXIMO_NOMBRE, 'regex:'.DatosDeInscrito::PATRON_NOMBRE],
            'apellidos' => ['required', 'string', 'max:'.DatosDeInscrito::MAXIMO_NOMBRE, 'regex:'.DatosDeInscrito::PATRON_NOMBRE],
            'carrera_id' => ['required', 'integer', 'exists:carreras,id'],
            'confirmo' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documento.required' => 'Obligatorio',
            'documento.regex' => DatosDeInscrito::MENSAJE_DOCUMENTO,
            'documento.max' => DatosDeInscrito::MENSAJE_DOCUMENTO,
            'nombres.required' => 'Obligatorio',
            'nombres.regex' => 'Escriba un nombre real: solo letras, mínimo 3',
            'apellidos.required' => 'Obligatorio',
            'apellidos.regex' => 'Escriba un nombre real: solo letras, mínimo 3',
            'carrera_id.required' => 'Elija su carrera',
            'carrera_id.exists' => 'Elija su carrera',
            'confirmo.accepted' => 'Confirme que revisó sus datos',
        ];
    }
}
