<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Asignatura extends Model
{
    protected $table = 'asignaturas';

    protected $fillable = [
        'codigo',
        'nombre',
        'semestre',
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    /**
     * @return HasMany<GrupoAsignatura, $this>
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(
            GrupoAsignatura::class,
            'asignatura_id'
        );
    }
}
