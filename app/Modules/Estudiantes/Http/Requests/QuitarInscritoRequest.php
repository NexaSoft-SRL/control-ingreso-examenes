<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use App\Modules\Estudiantes\Domain\Enums\MotivoBaja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El motivo por el que el docente quita a un inscrito. «Otro» exige un
 * detalle de 5 a 200 caracteres.
 */
final class QuitarInscritoRequest extends FormRequest
{
    public const DETALLE_MINIMO = 5;

    public const DETALLE_MAXIMO = 200;

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
            'motivo' => ['required', 'string', Rule::in(MotivoBaja::valores())],
            'detalle' => [
                'nullable',
                'required_if:motivo,'.MotivoBaja::Otro->value,
                'string',
                'min:'.self::DETALLE_MINIMO,
                'max:'.self::DETALLE_MAXIMO,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo es obligatorio.',
            'motivo.string' => 'El motivo no es válido.',
            'motivo.in' => 'El motivo debe ser RETIRO_MATERIA, CARGADO_POR_ERROR u OTRO.',
            'detalle.required_if' => 'El detalle es obligatorio cuando el motivo es Otro.',
            'detalle.string' => 'El detalle no es válido.',
            'detalle.min' => 'El detalle debe tener al menos '.self::DETALLE_MINIMO.' caracteres.',
            'detalle.max' => 'El detalle no puede superar los '.self::DETALLE_MAXIMO.' caracteres.',
        ];
    }

    public function motivo(): MotivoBaja
    {
        return MotivoBaja::from($this->string('motivo')->toString());
    }

    public function detalle(): ?string
    {
        $detalle = $this->string('detalle')->squish()->toString();

        return $detalle === '' ? null : $detalle;
    }
}
