<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ActivarCuentaDocenteRequest extends FormRequest
{
    use ValidaEnEspanol;

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
            'usuario' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9._-]+$/'],
            'correo' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $usuario = $this->input('usuario');
        $correo = $this->input('correo');

        // El usuario se guarda en minusculas: se valida ya normalizado.
        $this->merge([
            'usuario' => is_string($usuario) ? mb_strtolower(trim($usuario)) : $usuario,
            'correo' => is_string($correo) && trim($correo) !== '' ? mb_strtolower(trim($correo)) : null,
        ]);
    }

    public function usuario(): string
    {
        return $this->string('usuario')->toString();
    }

    public function correo(): ?string
    {
        return $this->textoOpcional('correo');
    }
}
