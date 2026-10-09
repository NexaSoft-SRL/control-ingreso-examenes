<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Models;

use App\Modules\Estudiantes\Domain\Enums\AlcanceCarga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un archivo de inscritos subido, con el resultado de procesarlo.
 *
 * @property int $id
 * @property AlcanceCarga $alcance
 * @property int|null $grupo_id
 * @property int|null $facultad_id
 * @property int $periodo_id
 * @property string $archivo
 * @property int $filas
 * @property int $nuevos
 * @property int $reutilizados
 * @property int $ya_inscritos
 * @property int $rechazados
 * @property int $conflictos
 * @property array<mixed>|null $rechazos
 * @property int $cargada_por
 * @property Carbon|null $created_at
 */
final class CargaInscritos extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cargas_inscritos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'alcance',
        'grupo_id',
        'facultad_id',
        'periodo_id',
        'archivo',
        'filas',
        'nuevos',
        'reutilizados',
        'ya_inscritos',
        'rechazados',
        'conflictos',
        'rechazos',
        'cargada_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alcance' => AlcanceCarga::class,
            'grupo_id' => 'integer',
            'facultad_id' => 'integer',
            'periodo_id' => 'integer',
            'filas' => 'integer',
            'nuevos' => 'integer',
            'reutilizados' => 'integer',
            'ya_inscritos' => 'integer',
            'rechazados' => 'integer',
            'conflictos' => 'integer',
            'rechazos' => 'array',
            'cargada_por' => 'integer',
        ];
    }

    /**
     * @return HasMany<Inscripcion, $this>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'carga_id');
    }
}
