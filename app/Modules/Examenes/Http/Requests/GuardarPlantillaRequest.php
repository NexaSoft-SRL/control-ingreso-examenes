<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use App\Modules\Examenes\Domain\Rules\TextoDeNorma;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El texto de una plantilla de normas. Que no repita otra de la cuenta lo
 * comprueba la accion.
 */
final class GuardarPlantillaRequest extends FormRequest
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
            'texto' => ['required', 'string', 'min:'.TextoDeNorma::MINIMO, 'max:'.TextoDeNorma::MAXIMO],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'texto.required' => 'Obligatorio',
            'texto.string' => 'Obligatorio',
            'texto.min' => 'Entre 3 y 300 caracteres',
            'texto.max' => 'Entre 3 y 300 caracteres',
        ];
    }

    public function texto(): string
    {
        return $this->string('texto')->toString();
    }
}
