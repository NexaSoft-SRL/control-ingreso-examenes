<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use App\Modules\Estudiantes\Domain\Rules\DatosDeInscrito;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El carnet que escribe el estudiante: no se puede modificar despues, asi
 * que debe confirmar que lo reviso.
 */
final class CarnetRequest extends FormRequest
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
            'confirmo.accepted' => 'Confirme que revisó sus datos',
        ];
    }
}
