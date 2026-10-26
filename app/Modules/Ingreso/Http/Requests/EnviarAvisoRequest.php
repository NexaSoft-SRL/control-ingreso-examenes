<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El aviso de un examen: las recomendaciones del docente y los mismos
 * filtros de la lista de codigos (vacio = todos los habilitados).
 */
final class EnviarAvisoRequest extends FormRequest
{
    public const MAXIMO_RECOMENDACIONES = 2000;

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
            'recomendaciones' => ['nullable', 'string', 'max:'.self::MAXIMO_RECOMENDACIONES],
            'grupo' => ['nullable', 'integer', 'min:1'],
            'aula' => ['nullable', 'integer', 'min:1'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'envio' => ['nullable', 'string', 'in:'.implode(',', FiltroCodigosData::ENVIOS)],
            'tanda' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recomendaciones.*' => 'Máximo '.self::MAXIMO_RECOMENDACIONES.' caracteres',
            'grupo.*' => 'El grupo no es válido.',
            'aula.*' => 'El aula no es válida.',
            'buscar.*' => 'La búsqueda admite hasta 100 caracteres.',
            'envio.*' => 'El estado de envío no es válido.',
            'tanda.*' => 'La tanda no es válida.',
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
            envio: is_string($envio) && $envio !== '' ? $envio : null,
        );
    }

    public function recomendaciones(): ?string
    {
        $texto = $this->validated('recomendaciones');

        return is_string($texto) ? $texto : null;
    }

    public function tanda(): ?string
    {
        $tanda = $this->validated('tanda');

        return is_string($tanda) && $tanda !== '' ? $tanda : null;
    }
}
