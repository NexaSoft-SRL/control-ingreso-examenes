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
            'documento.regex' => 'De 4 a 10 dígitos y su complemento («5003748-1A»)',
            'documento.max' => 'Demasiado largo',
            'nombres.required' => 'Obligatorio',
            'nombres.regex' => 'Solo letras, sin signos seguidos',
            'apellidos.required' => 'Obligatorio',
            'apellidos.regex' => 'Solo letras, sin signos seguidos',
            'carrera_id.required' => 'Elija su carrera',
            'carrera_id.exists' => 'Elija su carrera',
            'confirmo.accepted' => 'Confirme que revisó sus datos',
        ];
    }
}
