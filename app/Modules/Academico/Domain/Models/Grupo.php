<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un grupo de una asignatura en un periodo y una facultad. Sin docente es
 * un grupo «Por designar».
 *
 * @property int $id
 * @property int $periodo_id
 * @property int $asignatura_id
 * @property int $facultad_id
 * @property int|null $docente_id
 * @property string $codigo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Grupo extends Model
{
    protected $table = 'grupos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'periodo_id',
        'asignatura_id',
        'facultad_id',
        'docente_id',
        'codigo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'periodo_id' => 'integer',
            'asignatura_id' => 'integer',
            'facultad_id' => 'integer',
            'docente_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Periodo, $this>
     */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class, 'periodo_id');
    }

    /**
     * @return BelongsTo<Asignatura, $this>
     */
    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class, 'asignatura_id');
    }

    /**
     * @return BelongsTo<Facultad, $this>
     */
    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class, 'facultad_id');
    }

    /**
     * @return BelongsTo<Docente, $this>
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    /**
     * @return HasMany<Horario, $this>
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'grupo_id');
    }
}
