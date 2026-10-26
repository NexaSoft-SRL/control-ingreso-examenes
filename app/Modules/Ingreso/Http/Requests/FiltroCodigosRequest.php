<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los filtros de los codigos QR: sirven a la lista, al envio y a la hoja.
 * El envio admite ademas `tanda`, el instante que devolvio su primera
 * vuelta.
 */
final class FiltroCodigosRequest extends FormRequest
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
            'grupo' => ['nullable', 'integer', 'min:1'],
            'aula' => ['nullable', 'integer', 'min:1'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'envio' => ['nullable', 'string', 'in:'.implode(',', FiltroCodigosData::ENVIOS)],
            'tanda' => ['nullable', 'date'],
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
            'grupo.*' => 'El grupo no es válido.',
            'aula.*' => 'El aula no es válida.',
            'buscar.*' => 'La búsqueda admite hasta 100 caracteres.',
            'envio.*' => 'El estado de envío no es válido.',
            'tanda.*' => 'La tanda no es válida.',
            'pagina.*' => 'La página no es válida.',
            'por_pagina.*' => 'Entre 1 y 100 por página.',
        ];
    }

    public function toData(): FiltroCodigosData
    {
        $buscar = $this->validated('buscar');
        $envio = $this->validated('envio');

        return new FiltroCodigosData(
            grupo: $this->filled('grupo') ? $this->integer('grupo') : null,
            aula: $this->filled('aula') ? $this->integer('aula') : null,
            buscar: is_string($buscar) ? $buscar : null,
            pagina: $this->filled('pagina') ? $this->integer('pagina') : 1,
            porPagina: $this->filled('por_pagina') ? $this->integer('por_pagina') : 20,
            envio: is_string($envio) && $envio !== '' ? $envio : null,
        );
    }

    public function tanda(): ?string
    {
        $tanda = $this->validated('tanda');

        return is_string($tanda) && $tanda !== '' ? $tanda : null;
    }
}
