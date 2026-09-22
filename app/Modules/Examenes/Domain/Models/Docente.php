<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Docente extends Model
{
    protected $table = 'docentes';

    protected $fillable = [
        'user_id',
        'codigo_docente',
        'nombres',
        'apellidos',
        'correo',
        'telefono',
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
            'docente_id'
        );
    }
}
