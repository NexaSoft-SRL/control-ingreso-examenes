<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use App\Modules\Examenes\Application\DTOs\GrupoAsignaturaData;
use App\Modules\Examenes\Application\DTOs\RegistrarAsignaturaData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegistrarAsignaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('asignaturas', 'codigo'),
            ],
            'nombre' => [
                'required',
                'string',
                'max:150',
            ],
            'semestre' => [
                'nullable',
                'string',
                'max:20',
            ],
            'descripcion' => [
                'nullable',
                'string',
            ],
            'grupos' => [
                'required',
                'array',
                'min:1',
            ],
            'grupos.*.codigo_grupo' => [
                'required',
                'string',
                'max:20',
                'distinct',
            ],
            'grupos.*.docente_id' => [
                'required',
                'integer',
                Rule::exists('docentes', 'id')
                    ->where('estado', true),
            ],
            'grupos.*.cupo' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    public function toData(): RegistrarAsignaturaData
    {
        /**
         * @var array{
         *     codigo: string,
         *     nombre: string,
         *     semestre?: string|null,
         *     descripcion?: string|null,
         *     grupos: list<array{
         *         codigo_grupo: string,
         *         docente_id: int,
         *         cupo: int
         *     }>
         * } $validated
         */
        $validated = $this->validated();

        $grupos = array_map(
            static fn (array $grupo): GrupoAsignaturaData => new GrupoAsignaturaData(
                codigoGrupo: $grupo['codigo_grupo'],
                docenteId: $grupo['docente_id'],
                cupo: $grupo['cupo'],
            ),
            $validated['grupos'],
        );

        return new RegistrarAsignaturaData(
            codigo: $validated['codigo'],
            nombre: $validated['nombre'],
            semestre: $validated['semestre'] ?? null,
            descripcion: $validated['descripcion'] ?? null,
            grupos: $grupos,
        );
    }
}
