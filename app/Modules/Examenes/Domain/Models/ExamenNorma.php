<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una norma marcada en un examen. El texto es una copia del de la plantilla
 * al guardar: el examen la conserva aunque la plantilla se edite o se quite
 * (entonces `plantilla_id` queda nulo).
 *
 * @property int $id
 * @property int $examen_id
 * @property int|null $plantilla_id
 * @property string $texto
 * @property int $orden
 */
final class ExamenNorma extends Model
{
    public $timestamps = false;

    protected $table = 'examen_norma';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'plantilla_id',
        'texto',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'plantilla_id' => 'integer',
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Examen, $this>
     */
    public function examen(): BelongsTo
    {
        return $this->belongsTo(Examen::class, 'examen_id');
    }
}
