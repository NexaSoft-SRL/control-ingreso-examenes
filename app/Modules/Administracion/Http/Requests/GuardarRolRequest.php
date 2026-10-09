<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Application\Contracts\RolesGateway;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edicion de un rol. En la edicion (`PUT`) la ruta trae el
 * identificador y el nombre es opcional: sin el, solo cambian los
 * permisos.
 */
final class GuardarRolRequest extends FormRequest
{
    /**
     * `bloqueadas` es una clave de los conteos del listado de cuentas,
     * junto a los nombres de los roles.
     */
    private const RESERVADOS = ['bloqueadas'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');

        if (is_string($nombre)) {
            $this->merge([
                'nombre' => trim((string) preg_replace('/\s+/u', ' ', $nombre)),
            ]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(RolesGateway $roles): array
    {
        $propio = $this->rolId();

        return [
            'nombre' => [
                // En la edicion puede no viajar; si viaja, no va vacio.
                ...($propio === null ? [] : ['sometimes']),
                'required',
                'string',
                'min:3',
                'max:40',
                function (string $atributo, mixed $valor, Closure $fallar) use ($roles, $propio): void {
                    if (! is_string($valor)) {
                        return;
                    }

                    if (
                        in_array(mb_strtolower($valor), self::RESERVADOS, true)
                        || $roles->nombreRegistrado($valor, $propio)
                    ) {
                        $fallar('Ya existe');
                    }
                },
            ],
            'permisos' => ['required', 'array', 'min:1'],
            'permisos.*' => ['string', 'distinct', Rule::exists('permissions', 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Obligatorio',
            'nombre.string' => 'Obligatorio',
            'nombre.min' => 'Mínimo 3 caracteres',
            'nombre.max' => 'Máximo 40 caracteres',
            'permisos.required' => 'Al menos un permiso',
            'permisos.array' => 'Al menos un permiso',
            'permisos.min' => 'Al menos un permiso',
            'permisos.*.string' => 'El permiso no existe',
            'permisos.*.distinct' => 'Permiso repetido',
            'permisos.*.exists' => 'El permiso no existe',
        ];
    }

    /**
     * Null cuando el nombre no viaja (solo en la edicion).
     */
    public function nombre(): ?string
    {
        $nombre = $this->validated('nombre');

        return is_string($nombre) ? $nombre : null;
    }

    /**
     * @return list<string>
     */
    public function permisos(): array
    {
        $validados = $this->validated('permisos');
        $permisos = [];

        foreach (is_array($validados) ? $validados : [] as $clave) {
            if (is_string($clave)) {
                $permisos[] = $clave;
            }
        }

        return $permisos;
    }

    /**
     * El rol que se edita, para que su propio nombre no cuente como
     * repetido. Null en el alta.
     */
    private function rolId(): ?int
    {
        $id = $this->route('rol');

        return is_numeric($id) ? (int) $id : null;
    }
}
