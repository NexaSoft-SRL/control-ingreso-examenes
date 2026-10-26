<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use App\Modules\Estudiantes\Application\DTOs\PlazoFotoData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class PlazoFotoRequest extends FormRequest
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
            'desde' => ['required', 'date_format:Y-m-d'],
            // Un plazo ya vencido no sirve, y uno de mas de un semestre no es
            // un plazo.
            'hasta' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:desde',
                'after_or_equal:today',
                function (string $atributo, mixed $valor, Closure $fallar): void {
                    $desde = $this->string('desde')->toString();

                    if (is_string($valor) && strtotime($desde) !== false && strtotime($valor) !== false
                        && (strtotime($valor) - strtotime($desde)) / 86400 > 180) {
                        $fallar('Máximo 180 días');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Sin comodines: el mensaje del máximo de días es el de la regla.
            'desde.required' => 'Obligatorio',
            'desde.date_format' => 'Fecha no válida',
            'hasta.required' => 'Obligatorio',
            'hasta.date_format' => 'Fecha no válida',
            'hasta.after_or_equal' => 'No puede ser antes del inicio ni de hoy',
        ];
    }

    public function toData(): PlazoFotoData
    {
        return new PlazoFotoData(
            desde: $this->string('desde')->toString(),
            hasta: $this->string('hasta')->toString(),
        );
    }
}
