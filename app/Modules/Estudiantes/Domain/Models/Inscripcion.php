<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Models;

use App\Modules\Estudiantes\Domain\Enums\MotivoBaja;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un estudiante inscrito en un grupo. Es unica por estudiante y grupo.
 * Con `baja_en` el docente la quito del grupo: no se borra, pero deja de
 * contar.
 *
 * @property int $id
 * @property int $estudiante_id
 * @property int $grupo_id
 * @property OrigenEstudiante $via
 * @property int|null $cargada_por
 * @property int|null $carga_id
 * @property Carbon|null $baja_en
 * @property int|null $baja_por
 * @property MotivoBaja|null $motivo_baja
 * @property string|null $detalle_baja
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
        'baja_en',
        'baja_por',
        'motivo_baja',
        'detalle_baja',
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
            'baja_en' => 'datetime',
            'baja_por' => 'integer',
            'motivo_baja' => MotivoBaja::class,
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
