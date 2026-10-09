<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Los grupos dan las aulas sugeridas; la fecha, la hora y la duracion, el
 * aviso de aula compartida. `examen_id` excluye al propio examen.
 */
final class OpcionesDeAulasRequest extends FormRequest
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
            'grupos' => ['nullable', 'array'],
            'grupos.*' => ['integer', 'min:1'],
            'fecha' => ['nullable', 'date_format:Y-m-d', 'required_with:hora_inicio,duracion_minutos'],
            'hora_inicio' => ['nullable', 'date_format:H:i', 'required_with:fecha,duracion_minutos'],
            'duracion_minutos' => ['nullable', 'integer', 'between:15,480', 'required_with:fecha,hora_inicio'],
            'examen_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grupos.array' => 'Los grupos no son válidos.',
            'grupos.*.integer' => 'El grupo no es válido.',
            'grupos.*.min' => 'El grupo no es válido.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'fecha.required_with' => 'Indica la fecha.',
            'hora_inicio.date_format' => 'La hora debe tener el formato HH:MM.',
            'hora_inicio.required_with' => 'Indica la hora de inicio.',
            'duracion_minutos.integer' => 'Entre 15 y 480',
            'duracion_minutos.between' => 'Entre 15 y 480',
            'duracion_minutos.required_with' => 'Indica la duración.',
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

    public function texto(string $campo): ?string
    {
        $valor = $this->validated($campo);

        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    public function entero(string $campo): ?int
    {
        $valor = $this->validated($campo);

        return is_numeric($valor) ? (int) $valor : null;
    }
}
