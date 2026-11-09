<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use App\Modules\Examenes\Application\DTOs\AulaDeExamenData;
use App\Modules\Examenes\Application\DTOs\GuardarExamenData;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El asistente de registro entero: datos, normas, grupos, aulas y quien
 * atiende.
 * Aqui va la forma; lo que depende de lo ya registrado lo comprueba la
 * accion. No se valida capacidad ni solape de aulas.
 */
final class GuardarExamenRequest extends FormRequest
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
            'asignatura_id' => ['required', 'integer', 'exists:asignaturas,id'],
            'tipo' => ['required', 'string', Rule::in(TipoExamen::valores())],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracion_minutos' => ['required', 'integer', 'between:15,480'],
            'normas' => ['nullable', 'string', 'max:2000'],
            // Ids de plantillas: predefinidas o propias de quien registra.
            'normas_marcadas' => ['nullable', 'array', 'max:50'],
            'normas_marcadas.*' => ['integer', 'distinct', 'min:1'],
            // Ids de normas del examen cuya plantilla ya no existe.
            'normas_conservadas' => ['nullable', 'array'],
            'normas_conservadas.*' => ['integer', 'min:1'],
            'grupos' => ['required', 'array', 'min:1'],
            'grupos.*' => ['integer', 'distinct', 'exists:grupos,id'],
            'aulas' => ['nullable', 'array'],
            'aulas.*' => ['array'],
            'aulas.*.aula_id' => ['required', 'integer', 'distinct', 'exists:aulas,id'],
            'aulas.*.encargado_id' => ['nullable', 'integer'],
            'aulas.*.apoyo' => ['nullable', 'array'],
            'aulas.*.apoyo.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asignatura_id.required' => 'Elija la asignatura.',
            'asignatura_id.integer' => 'La asignatura no es válida.',
            'asignatura_id.exists' => 'La asignatura no existe.',
            'tipo.required' => 'Elija el tipo de examen.',
            'tipo.string' => 'El tipo de examen no es válido.',
            'tipo.in' => 'El tipo de examen no es válido.',
            'fecha.required' => 'Indique la fecha.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'hora_inicio.required' => 'Indique la hora de inicio.',
            'hora_inicio.date_format' => 'La hora debe tener el formato HH:MM.',
            'duracion_minutos.required' => 'Indique la duración.',
            'duracion_minutos.integer' => 'Entre 15 y 480',
            'duracion_minutos.between' => 'Entre 15 y 480',
            'normas.string' => 'Las normas deben ser un texto.',
            'normas.max' => 'Las normas admiten hasta 2.000 caracteres.',
            'normas_marcadas.array' => 'Las normas no son válidas.',
            'normas_marcadas.max' => 'Hasta 50 normas marcadas.',
            'normas_marcadas.*.integer' => 'La norma no es válida.',
            'normas_marcadas.*.distinct' => 'Hay una norma repetida.',
            'normas_marcadas.*.min' => 'La norma no es válida.',
            'normas_conservadas.array' => 'Las normas no son válidas.',
            'normas_conservadas.*.integer' => 'La norma no es válida.',
            'normas_conservadas.*.min' => 'La norma no es válida.',
            'grupos.required' => 'Elija al menos un grupo.',
            'grupos.array' => 'Elija al menos un grupo.',
            'grupos.min' => 'Elija al menos un grupo.',
            'grupos.*.integer' => 'El grupo no es válido.',
            'grupos.*.distinct' => 'Hay un grupo repetido.',
            'grupos.*.exists' => 'El grupo no existe.',
            'aulas.array' => 'Las aulas no son válidas.',
            'aulas.*.array' => 'El aula no es válida.',
            'aulas.*.aula_id.required' => 'Falta el aula.',
            'aulas.*.aula_id.integer' => 'El aula no es válida.',
            'aulas.*.aula_id.distinct' => 'Hay un aula repetida.',
            'aulas.*.aula_id.exists' => 'El aula no existe.',
            'aulas.*.encargado_id.integer' => 'El encargado no es válido.',
            'aulas.*.apoyo.array' => 'El apoyo no es válido.',
            'aulas.*.apoyo.*.integer' => 'El apoyo no es válido.',
        ];
    }

    public function toData(): GuardarExamenData
    {
        $normas = $this->validated('normas');
        $normas = is_string($normas) ? trim($normas) : '';

        $conservadas = $this->validated('normas_conservadas');

        $aulas = [];
        $recibidas = $this->validated('aulas');

        foreach (is_array($recibidas) ? $recibidas : [] as $aula) {
            if (! is_array($aula)) {
                continue;
            }

            $encargado = $aula['encargado_id'] ?? null;

            $aulas[] = new AulaDeExamenData(
                aulaId: $this->entero($aula['aula_id'] ?? null),
                encargadoId: $encargado === null ? null : $this->entero($encargado),
                apoyo: $this->enteros($aula['apoyo'] ?? null),
            );
        }

        return new GuardarExamenData(
            asignaturaId: $this->integer('asignatura_id'),
            tipo: $this->string('tipo')->toString(),
            fecha: $this->string('fecha')->toString(),
            horaInicio: $this->string('hora_inicio')->toString(),
            duracionMinutos: $this->integer('duracion_minutos'),
            normas: $normas === '' ? null : $normas,
            grupos: $this->enteros($this->validated('grupos')),
            aulas: $aulas,
            normasMarcadas: $this->enteros($this->validated('normas_marcadas')),
            normasConservadas: is_array($conservadas) ? $this->enteros($conservadas) : null,
        );
    }

    /**
     * @return list<int>
     */
    private function enteros(mixed $valores): array
    {
        $enteros = [];

        foreach (is_array($valores) ? $valores : [] as $valor) {
            $enteros[] = $this->entero($valor);
        }

        return array_values(array_unique($enteros));
    }

    private function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }
}
