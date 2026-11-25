<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use App\Modules\Examenes\Domain\Enums\TipoExamen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Los grupos y el tipo del examen que se arma; el propio examen (`examen_id`)
 * no cuenta como otro.
 */
final class OpcionesDeOcupadosRequest extends FormRequest
{
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
            'grupos' => ['required', 'array', 'min:1'],
            'grupos.*' => ['integer', 'min:1'],
            'tipo' => ['required', 'string', Rule::enum(TipoExamen::class)],
            'examen_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grupos.required' => 'Elija al menos un grupo.',
            'grupos.array' => 'Los grupos no son válidos.',
            'grupos.min' => 'Elija al menos un grupo.',
            'grupos.*.integer' => 'El grupo no es válido.',
            'grupos.*.min' => 'El grupo no es válido.',
            'tipo.required' => 'Elija el tipo de examen.',
            'tipo.enum' => 'El tipo de examen no es válido.',
            'examen_id.integer' => 'El examen no es válido.',
            'examen_id.min' => 'El examen no es válido.',
        ];
    }

    /**
     * @return list<int>
     */
    public function grupos(): array
    {
        $grupos = [];
        $recibidos = $this->validated('grupos');

        foreach (is_array($recibidos) ? $recibidos : [] as $grupo) {
            if (is_numeric($grupo)) {
                $grupos[] = (int) $grupo;
            }
        }

        return array_values(array_unique($grupos));
    }

    public function tipo(): TipoExamen
    {
        return TipoExamen::from($this->string('tipo')->toString());
    }

    public function examenId(): ?int
    {
        $valor = $this->validated('examen_id');

        return is_numeric($valor) ? (int) $valor : null;
    }
}
