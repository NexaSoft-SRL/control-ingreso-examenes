<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Domain\Enums\EstadoConflicto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pagina, busqueda y filtros de los listados del modulo (rutas 34, 37 y
 * 40). Cada ruta usa los filtros que le tocan.
 */
final class ListaRequest extends FormRequest
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
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'facultad' => ['sometimes', 'nullable', 'string', 'max:10'],
            'carrera' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'estado' => ['sometimes', 'nullable', 'string', 'in:pendiente,resuelto,todos'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pagina.integer' => 'La página debe ser un número.',
            'pagina.min' => 'La página debe ser 1 o más.',
            'por_pagina.integer' => 'El tamaño de página debe ser un número.',
            'por_pagina.min' => 'El tamaño de página debe ser 1 o más.',
            'por_pagina.max' => 'El tamaño de página no puede pasar de 100.',
            'buscar.max' => 'La búsqueda no puede pasar de 100 caracteres.',
            'facultad.max' => 'La facultad no es válida.',
            'carrera.integer' => 'La carrera no es válida.',
            'carrera.min' => 'La carrera no es válida.',
            'estado.in' => 'El estado debe ser pendiente, resuelto o todos.',
        ];
    }

    public function toData(int $porPagina = 25): FiltroListaData
    {
        $buscar = $this->validated('buscar');
        $facultad = $this->validated('facultad');
        $carrera = $this->validated('carrera');

        $estado = match ($this->validated('estado')) {
            'resuelto' => EstadoConflicto::Resuelto->value,
            'todos' => null,
            default => EstadoConflicto::Pendiente->value,
        };

        return new FiltroListaData(
            pagina: $this->has('pagina') ? $this->integer('pagina') : 1,
            porPagina: $this->has('por_pagina') ? $this->integer('por_pagina') : $porPagina,
            buscar: is_string($buscar) && trim($buscar) !== '' ? trim($buscar) : null,
            facultad: is_string($facultad) && trim($facultad) !== '' ? trim($facultad) : null,
            carreraId: is_numeric($carrera) ? (int) $carrera : null,
            estado: $estado,
        );
    }
}
