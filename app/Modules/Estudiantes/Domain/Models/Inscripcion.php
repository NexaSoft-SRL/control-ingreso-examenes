<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Models;

use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un estudiante inscrito en un grupo. Es unica por estudiante y grupo.
 *
 * @property int $id
 * @property int $estudiante_id
 * @property int $grupo_id
 * @property OrigenEstudiante $via
 * @property int|null $cargada_por
 * @property int|null $carga_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Inscripcion extends Model
{
    protected $table = 'inscripciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'estudiante_id',
        'grupo_id',
        'via',
        'cargada_por',
        'carga_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estudiante_id' => 'integer',
            'grupo_id' => 'integer',
            'via' => OrigenEstudiante::class,
            'cargada_por' => 'integer',
            'carga_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Estudiante, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    /**
     * @return BelongsTo<CargaInscritos, $this>
     */
    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaInscritos::class, 'carga_id');
    }
}
