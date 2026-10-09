<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Application\DTOs\FiltroUsuariosData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListarUsuariosRequest extends FormRequest
{
    private const POR_PAGINA = 20;

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
            'buscar' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'bloqueadas' => ['nullable', 'boolean'],
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
            'buscar.max' => 'Máximo 100 caracteres.',
            'rol.exists' => 'El rol no existe.',
            'bloqueadas.boolean' => 'El filtro de bloqueadas no es válido.',
            'pagina.integer' => 'La página no es válida.',
            'pagina.min' => 'La página no es válida.',
            'por_pagina.integer' => 'El tamaño de página no es válido.',
            'por_pagina.min' => 'El tamaño de página no es válido.',
            'por_pagina.max' => 'Máximo 100 filas por página.',
        ];
    }

    public function toData(): FiltroUsuariosData
    {
        $buscar = $this->validated('buscar');
        $rol = $this->validated('rol');

        return new FiltroUsuariosData(
            buscar: is_string($buscar) ? $buscar : null,
            rol: is_string($rol) ? $rol : null,
            soloBloqueadas: $this->boolean('bloqueadas'),
            pagina: max(1, $this->integer('pagina', 1)),
            porPagina: max(1, $this->integer('por_pagina', self::POR_PAGINA)),
        );
    }
}
