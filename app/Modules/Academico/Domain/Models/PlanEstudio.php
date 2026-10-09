<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * En que nivel de una carrera se dicta una asignatura.
 *
 * @property int $id
 * @property int $carrera_id
 * @property int $asignatura_id
 * @property string $nivel
 */
final class PlanEstudio extends Model
{
    public $timestamps = false;

    protected $table = 'plan_estudios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'carrera_id',
        'asignatura_id',
        'nivel',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'carrera_id' => 'integer',
            'asignatura_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Carrera, $this>
     */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    /**
     * @return BelongsTo<Asignatura, $this>
     */
    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class, 'asignatura_id');
    }
}
