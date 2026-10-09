<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una sesion semanal de un grupo. Se borran y recrean en cada importacion.
 *
 * @property int $id
 * @property int $grupo_id
 * @property int|null $aula_id
 * @property string $dia
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property bool $es_auxiliatura
 */
final class Horario extends Model
{
    public $timestamps = false;

    protected $table = 'horarios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_id',
        'aula_id',
        'dia',
        'hora_inicio',
        'hora_fin',
        'es_auxiliatura',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grupo_id' => 'integer',
            'aula_id' => 'integer',
            'es_auxiliatura' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Grupo, $this>
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * @return BelongsTo<Aula, $this>
     */
    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class, 'aula_id');
    }
}
