<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GrupoAsignatura extends Model
{
    protected $table = 'grupos_asignatura';

    protected $fillable = [
        'asignatura_id',
        'docente_id',
        'codigo_grupo',
        'cupo',
    ];

    protected function casts(): array
    {
        return [
            'cupo' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Asignatura, $this>
     */
    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(
            Asignatura::class,
            'asignatura_id'
        );
    }

    /**
     * @return BelongsTo<Docente, $this>
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(
            Docente::class,
            'docente_id'
        );
    }
}
