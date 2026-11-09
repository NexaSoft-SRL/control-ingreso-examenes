<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Una incidencia de la lista de la cuenta: un tipo del sistema con la
 * gravedad que prefiere, o una propia por su nombre.
 */
final class GuardarIncidenciaComunRequest extends FormRequest
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
            'tipo' => ['required_without:nombre', 'nullable', 'string', Rule::in(array_values(array_diff(TipoIncidencia::reportables(), [TipoIncidencia::Otra->value])))],
            'nombre' => ['required_without:tipo', 'nullable', 'string', 'min:3', 'max:80'],
            'gravedad' => ['required', 'string', Rule::in(Gravedad::valores())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.*' => 'Elija un tipo o escriba el nombre.',
            'nombre.required_without' => 'Obligatorio',
            'nombre.min' => 'Mínimo 3 caracteres',
            'nombre.max' => 'Máximo 80 caracteres',
            'gravedad.*' => 'Elija leve, grave o muy grave.',
        ];
    }

    public function tipo(): ?TipoIncidencia
    {
        return TipoIncidencia::tryFrom($this->string('tipo')->toString());
    }

    public function nombre(): ?string
    {
        $nombre = trim($this->string('nombre')->toString());

        return $nombre === '' ? null : $nombre;
    }

    public function gravedad(): Gravedad
    {
        return Gravedad::from($this->string('gravedad')->toString());
    }
}
