<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Application\DTOs\ConsultarBitacoraData;
use Illuminate\Foundation\Http\FormRequest;

final class ConsultarBitacoraRequest extends FormRequest
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
            'usuario_id' => [
                'sometimes',
                'integer',
                'min:1',
            ],
            'fecha' => [
                'sometimes',
                'date_format:Y-m-d',
            ],
            'desde' => [
                'sometimes',
                'date_format:Y-m-d',
            ],
            'hasta' => [
                'sometimes',
                'date_format:Y-m-d',
                'after_or_equal:desde',
            ],
            'operacion' => [
                'sometimes',
                'string',
                'max:100',
            ],
        ];
    }

    public function toData(): ConsultarBitacoraData
    {
        $usuarioId = $this->has('usuario_id')
            ? $this->integer('usuario_id')
            : null;

        $fecha = $this->validated('fecha');
        $operacion = $this->validated('operacion');
        $desde = $this->validated('desde');
        $hasta = $this->validated('hasta');

        return new ConsultarBitacoraData(
            usuarioId: $usuarioId,
            fecha: is_string($fecha)
                ? $fecha
                : null,
            operacion: is_string($operacion)
                ? $operacion
                : null,
            desde: is_string($desde)
                ? $desde
                : null,
            hasta: is_string($hasta)
                ? $hasta
                : null,
        );
    }
}
