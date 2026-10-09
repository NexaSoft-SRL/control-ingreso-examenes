<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Http\Requests;

use App\Modules\Habilitacion\Application\DTOs\CambioHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\FiltroHabilitacionData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * «Habilitar» / «Inhabilitar»: sobre una lista de estudiantes o, con
 * `todos`, sobre todo lo que dejan los filtros de la lista.
 */
final class CambiarHabilitacionRequest extends FormRequest
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
        $reglas = [
            'habilitado' => ['required', 'boolean'],
            'todos' => ['sometimes', 'boolean'],
        ];

        // El motivo solo cuenta al inhabilitar; al habilitar se ignora.
        if ($this->has('habilitado') && ! $this->boolean('habilitado')) {
            $reglas['motivo'] = ['required', 'string', 'min:5', 'max:1000'];
        }

        if ($this->boolean('todos')) {
            return $reglas + [
                'filtros' => ['sometimes', 'array'],
                'filtros.grupo' => ['nullable', 'integer', 'min:1'],
                'filtros.aula' => ['nullable', 'integer', 'min:1'],
                'filtros.condicion' => ['nullable', 'string', Rule::in(FiltroHabilitacionData::CONDICIONES)],
                'filtros.buscar' => ['nullable', 'string', 'max:100'],
            ];
        }

        return $reglas + [
            'estudiantes' => ['required', 'array', 'min:1', 'max:5000'],
            'estudiantes.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'habilitado.required' => 'Indica si se habilita o se inhabilita.',
            'habilitado.boolean' => 'Indica si se habilita o se inhabilita.',
            'todos.boolean' => 'El indicador «todos» no es válido.',
            'motivo.required' => 'Mínimo 5 caracteres',
            'motivo.string' => 'Mínimo 5 caracteres',
            'motivo.min' => 'Mínimo 5 caracteres',
            'motivo.max' => 'Máximo 1000 caracteres',
            'estudiantes.required' => 'Selecciona al menos un estudiante.',
            'estudiantes.array' => 'Selecciona al menos un estudiante.',
            'estudiantes.min' => 'Selecciona al menos un estudiante.',
            'estudiantes.max' => 'Máximo 5000 estudiantes por lote.',
            'estudiantes.*.integer' => 'La selección tiene un estudiante que no es válido.',
            'estudiantes.*.min' => 'La selección tiene un estudiante que no es válido.',
            'filtros.array' => 'Los filtros no son válidos.',
            'filtros.grupo.integer' => 'El grupo no es válido.',
            'filtros.grupo.min' => 'El grupo no es válido.',
            'filtros.aula.integer' => 'El aula no es válida.',
            'filtros.aula.min' => 'El aula no es válida.',
            'filtros.condicion.string' => 'La condición debe ser habilitado, no o pendiente.',
            'filtros.condicion.in' => 'La condición debe ser habilitado, no o pendiente.',
            'filtros.buscar.string' => 'El texto de búsqueda no es válido.',
            'filtros.buscar.max' => 'Máximo 100 caracteres.',
        ];
    }

    public function toData(): CambioHabilitacionData
    {
        $habilitado = $this->boolean('habilitado');
        $todos = $this->boolean('todos');

        $motivo = $this->validated('motivo');
        $condicion = $this->validated('filtros.condicion');
        $buscar = $this->validated('filtros.buscar');

        $estudiantes = [];

        if (! $todos) {
            $recibidos = $this->validated('estudiantes');

            foreach (is_array($recibidos) ? $recibidos : [] as $id) {
                if (is_numeric($id)) {
                    $estudiantes[] = (int) $id;
                }
            }
        }

        return new CambioHabilitacionData(
            habilitado: $habilitado,
            motivo: ! $habilitado && is_string($motivo) ? trim($motivo) : null,
            estudiantes: $estudiantes,
            todos: $todos,
            filtros: $todos
                ? new FiltroHabilitacionData(
                    grupoId: $this->filled('filtros.grupo') ? $this->integer('filtros.grupo') : null,
                    aulaId: $this->filled('filtros.aula') ? $this->integer('filtros.aula') : null,
                    condicion: is_string($condicion) && $condicion !== '' ? $condicion : null,
                    buscar: is_string($buscar) && trim($buscar) !== '' ? trim($buscar) : null,
                )
                : new FiltroHabilitacionData,
        );
    }
}
