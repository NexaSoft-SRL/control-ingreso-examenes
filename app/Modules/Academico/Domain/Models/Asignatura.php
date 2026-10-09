<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * La asignatura no tiene nivel propio: depende de la carrera y va en el
 * plan de estudios.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Asignatura extends Model
{
    protected $table = 'asignaturas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
    ];

    /**
     * @return HasMany<Grupo, $this>
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'asignatura_id');
    }

    /**
     * @return HasMany<PlanEstudio, $this>
     */
    public function planes(): HasMany
    {
        return $this->hasMany(PlanEstudio::class, 'asignatura_id');
    }
}
