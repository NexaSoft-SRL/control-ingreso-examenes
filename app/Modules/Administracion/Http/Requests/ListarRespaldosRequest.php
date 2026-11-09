<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Application\Queries\ListarRespaldos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListarRespaldosRequest extends FormRequest
{
    private const POR_PAGINA = 15;

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
            'filtro' => ['nullable', 'string', Rule::in(ListarRespaldos::FILTROS)],
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
            'filtro.in' => 'El filtro no existe.',
            'pagina.integer' => 'La página no es válida.',
            'pagina.min' => 'La página no es válida.',
            'por_pagina.integer' => 'El tamaño de página no es válido.',
            'por_pagina.min' => 'El tamaño de página no es válido.',
            'por_pagina.max' => 'No se pueden pedir más de 100 filas por página.',
        ];
    }

    public function filtro(): ?string
    {
        $filtro = $this->validated('filtro');

        return is_string($filtro) && $filtro !== '' ? $filtro : null;
    }

    public function pagina(): int
    {
        return max(1, $this->integer('pagina', 1));
    }

    public function porPagina(): int
    {
        return max(1, $this->integer('por_pagina', self::POR_PAGINA));
    }
}
