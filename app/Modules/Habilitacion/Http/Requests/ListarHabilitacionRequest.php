<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Http\Requests;

use App\Modules\Habilitacion\Application\DTOs\FiltroHabilitacionData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListarHabilitacionRequest extends FormRequest
{
    public const POR_PAGINA = 25;

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
            'grupo' => ['nullable', 'integer', 'min:1'],
            'aula' => ['nullable', 'integer', 'min:1'],
            'condicion' => ['nullable', 'string', Rule::in(FiltroHabilitacionData::CONDICIONES)],
            'buscar' => ['nullable', 'string', 'max:100'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grupo.integer' => 'El grupo no es válido.',
            'grupo.min' => 'El grupo no es válido.',
            'aula.integer' => 'El aula no es válida.',
            'aula.min' => 'El aula no es válida.',
            'condicion.string' => 'La condición debe ser habilitado, no o pendiente.',
            'condicion.in' => 'La condición debe ser habilitado, no o pendiente.',
            'buscar.string' => 'El texto de búsqueda no es válido.',
            'buscar.max' => 'Máximo 100 caracteres.',
            'pagina.integer' => 'La página debe ser un número.',
            'pagina.min' => 'La página empieza en 1.',
            'por_pagina.integer' => 'El tamaño de página debe ser un número.',
            'por_pagina.min' => 'Entre 1 y 100 por página.',
            'por_pagina.max' => 'Entre 1 y 100 por página.',
        ];
    }

    public function filtros(): FiltroHabilitacionData
    {
        $condicion = $this->validated('condicion');
        $buscar = $this->validated('buscar');

        return new FiltroHabilitacionData(
            grupoId: $this->filled('grupo') ? $this->integer('grupo') : null,
            aulaId: $this->filled('aula') ? $this->integer('aula') : null,
            condicion: is_string($condicion) && $condicion !== '' ? $condicion : null,
            buscar: is_string($buscar) && trim($buscar) !== '' ? trim($buscar) : null,
        );
    }

    public function pagina(): int
    {
        return $this->filled('pagina') ? $this->integer('pagina') : 1;
    }

    public function porPagina(): int
    {
        return $this->filled('por_pagina') ? $this->integer('por_pagina') : self::POR_PAGINA;
    }
}
