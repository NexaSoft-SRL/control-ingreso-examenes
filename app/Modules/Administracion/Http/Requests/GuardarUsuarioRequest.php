<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\DTOs\ActualizarCuentaData;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;
use App\Modules\Administracion\Domain\Rules\NombreDeUsuario;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edicion de una cuenta. En la edicion (`PUT`) la ruta trae el
 * identificador y puede viajar ademas `activo`.
 */
final class GuardarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El usuario y el correo no distinguen mayusculas: se guardan y se
     * comparan en minusculas.
     */
    protected function prepareForValidation(): void
    {
        $limpios = [];

        foreach (['nombre', 'usuario', 'correo'] as $campo) {
            $valor = $this->input($campo);

            if (is_string($valor)) {
                $limpios[$campo] = match ($campo) {
                    'nombre' => trim($valor),
                    'usuario' => NombreDeUsuario::normalizar($valor),
                    default => mb_strtolower(trim($valor)),
                };
            }
        }

        if (($limpios['correo'] ?? null) === '') {
            $limpios['correo'] = null;
        }

        $this->merge($limpios);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(CuentaUsuarioGateway $cuentas): array
    {
        $propia = $this->usuarioId();

        return [
            // Letras, espacios, puntos, apóstrofos y guiones; empieza con letra.
            'nombre' => ['required', 'string', 'min:3', 'max:255', "regex:/^(?=(?:.*\\p{L}){2})\\p{L}+(?:(?:\\.\\s*|\\s+|['-])\\p{L}+)*\\.?$/u"],
            'usuario' => [
                'required',
                'string',
                'max:60',
                // Nombre y apellido con un espacio («jose soruco»); tambien
                // punto o guion. Sin separadores seguidos ni en los bordes.
                'regex:'.NombreDeUsuario::FORMATO,
                function (string $atributo, mixed $valor, Closure $fallar) use ($cuentas, $propia): void {
                    if (is_string($valor) && $cuentas->usuarioRegistrado($valor, $propia)) {
                        $fallar('Ya existe');
                    }
                },
            ],
            'correo' => [
                'nullable',
                'string',
                'email',
                'max:255',
                function (string $atributo, mixed $valor, Closure $fallar) use ($cuentas, $propia): void {
                    if (is_string($valor) && $cuentas->correoRegistrado($valor, $propia)) {
                        $fallar('Ya existe');
                    }
                },
            ],
            // Cualquier rol existente, de inicio o creado.
            'rol' => ['required', 'string', Rule::exists('roles', 'name')],
            'activo' => ['sometimes', 'boolean'],
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
            'nombre.max' => 'Máximo 255 caracteres',
            'nombre.regex' => 'Solo letras, sin signos seguidos',
            'usuario.required' => 'Obligatorio',
            'usuario.string' => 'Obligatorio',
            'usuario.max' => 'Máximo 60 caracteres',
            'usuario.regex' => 'Nombre y apellido, separados por un espacio',
            'correo.string' => 'Correo no válido',
            'correo.email' => 'Correo no válido',
            'correo.max' => 'Máximo 255 caracteres',
            'rol.required' => 'Obligatorio',
            'rol.string' => 'El rol no existe',
            'rol.exists' => 'El rol no existe',
            'activo.boolean' => 'El estado no es válido',
        ];
    }

    public function toNuevaCuenta(): NuevaCuentaData
    {
        return new NuevaCuentaData(
            nombre: $this->string('nombre')->toString(),
            usuario: $this->string('usuario')->toString(),
            correo: $this->correo(),
            rol: $this->string('rol')->toString(),
        );
    }

    public function toActualizacion(int $usuarioId): ActualizarCuentaData
    {
        return new ActualizarCuentaData(
            usuarioId: $usuarioId,
            nombre: $this->string('nombre')->toString(),
            usuario: $this->string('usuario')->toString(),
            correo: $this->correo(),
            rol: $this->string('rol')->toString(),
            activo: $this->has('activo') ? $this->boolean('activo') : null,
        );
    }

    private function correo(): ?string
    {
        $correo = $this->validated('correo');

        return is_string($correo) && $correo !== '' ? $correo : null;
    }

    /**
     * La cuenta que se edita, para que su propio usuario y su propio
     * correo no cuenten como repetidos. Null en el alta.
     */
    private function usuarioId(): ?int
    {
        $id = $this->route('usuario');

        return is_numeric($id) ? (int) $id : null;
    }
}
