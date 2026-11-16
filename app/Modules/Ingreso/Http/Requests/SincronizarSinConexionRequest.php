<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Application\Actions\SincronizarSinConexion;
use App\Modules\Ingreso\Application\DTOs\EventoSinConexionData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lo que un puesto decidio sin conexion, para que el servidor lo aplique y lo
 * revise.
 */
final class SincronizarSinConexionRequest extends FormRequest
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
            'dispositivo' => ['required', 'string', 'min:8', 'max:64'],
            'instantanea_en' => ['required', 'date'],
            'eventos' => ['present', 'array', 'max:'.SincronizarSinConexion::MAXIMO_EVENTOS],
            'eventos.*.uid' => ['required', 'string', 'min:8', 'max:64'],
            'eventos.*.hora' => ['required', 'date'],
            'eventos.*.medio' => ['nullable', 'string', 'max:12'],
            'eventos.*.resultado' => ['required', 'string', Rule::in(SincronizarSinConexion::RESULTADOS)],
            'eventos.*.examen_id' => ['nullable', 'integer', 'min:1'],
            'eventos.*.estudiante_id' => ['nullable', 'integer', 'min:1'],
            'eventos.*.token' => ['nullable', 'string', 'max:100'],
            'eventos.*.detalle' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dispositivo.*' => 'Falta el identificador del puesto.',
            'instantanea_en.*' => 'Falta la instantánea que se descargó.',
            'eventos.present' => 'Faltan los eventos.',
            'eventos.array' => 'Los eventos no son válidos.',
            'eventos.max' => 'Demasiados eventos en un envío.',
            'eventos.*.uid.*' => 'Cada evento lleva su identificador.',
            'eventos.*.hora.*' => 'Cada evento lleva su hora.',
            'eventos.*.resultado.*' => 'El resultado de un evento no es válido.',
        ];
    }

    /**
     * Los eventos ya validados, en el orden en que llegaron.
     *
     * @return list<EventoSinConexionData>
     */
    public function eventos(): array
    {
        $eventos = [];

        foreach ($this->array('eventos') as $evento) {
            if (! is_array($evento)) {
                continue;
            }

            $eventos[] = new EventoSinConexionData(
                uid: self::texto($evento['uid'] ?? null) ?? '',
                hora: self::texto($evento['hora'] ?? null) ?? '',
                resultado: self::texto($evento['resultado'] ?? null) ?? '',
                medio: self::texto($evento['medio'] ?? null),
                examenId: is_numeric($evento['examen_id'] ?? null) ? (int) $evento['examen_id'] : null,
                estudianteId: is_numeric($evento['estudiante_id'] ?? null) ? (int) $evento['estudiante_id'] : null,
                token: self::texto($evento['token'] ?? null),
                detalle: self::texto($evento['detalle'] ?? null),
            );
        }

        return $eventos;
    }

    private static function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
