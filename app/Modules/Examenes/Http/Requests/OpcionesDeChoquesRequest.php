<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Los grupos, la fecha, la hora y la duracion del examen que se arma; el
 * propio examen (`examen_id`) no cuenta como otro examen.
 */
final class OpcionesDeChoquesRequest extends FormRequest
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
            'grupos' => ['required', 'array', 'min:1'],
            'grupos.*' => ['integer', 'min:1'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracion_minutos' => ['required', 'integer', 'between:15,480'],
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
            'fecha.required' => 'Indique la fecha.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'hora_inicio.required' => 'Indique la hora de inicio.',
            'hora_inicio.date_format' => 'La hora debe tener el formato HH:MM.',
            'duracion_minutos.required' => 'Indique la duración.',
            'duracion_minutos.integer' => 'Entre 15 y 480',
            'duracion_minutos.between' => 'Entre 15 y 480',
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

    public function texto(string $campo): string
    {
        $valor = $this->validated($campo);

        return is_string($valor) ? $valor : '';
    }

    public function entero(string $campo): ?int
    {
        $valor = $this->validated($campo);

        return is_numeric($valor) ? (int) $valor : null;
    }
}
