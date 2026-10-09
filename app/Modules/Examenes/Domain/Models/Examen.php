<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use App\Modules\Examenes\Domain\Enums\TipoExamen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * El examen es de la asignatura y abarca grupos. Solo quien lo registro lo
 * modifica o lo elimina.
 *
 * @property int $id
 * @property int $periodo_id
 * @property int $asignatura_id
 * @property TipoExamen $tipo
 * @property Carbon $fecha
 * @property string $hora_inicio
 * @property int $duracion_minutos
 * @property string|null $normas
 * @property int $creado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Examen extends Model
{
    protected $table = 'examenes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'periodo_id',
        'asignatura_id',
        'tipo',
        'fecha',
        'hora_inicio',
        'duracion_minutos',
        'normas',
        'creado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'periodo_id' => 'integer',
            'asignatura_id' => 'integer',
            'tipo' => TipoExamen::class,
            'fecha' => 'date',
            'duracion_minutos' => 'integer',
            'creado_por' => 'integer',
        ];
    }

    /**
     * @return HasMany<ExamenGrupo, $this>
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(ExamenGrupo::class, 'examen_id');
    }

    /**
     * @return HasMany<ExamenAula, $this>
     */
    public function aulas(): HasMany
    {
        return $this->hasMany(ExamenAula::class, 'examen_id');
    }

    /**
     * Las normas marcadas de plantillas, en el orden en que se muestran.
     *
     * @return HasMany<ExamenNorma, $this>
     */
    public function normasMarcadas(): HasMany
    {
        return $this->hasMany(ExamenNorma::class, 'examen_id')
            ->orderBy('orden');
    }
}
