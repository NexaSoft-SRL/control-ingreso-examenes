<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un aula del examen. No lleva tope de estudiantes y dos examenes pueden
 * compartirla a la misma hora.
 *
 * @property int $id
 * @property int $examen_id
 * @property int $aula_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ExamenAula extends Model
{
    protected $table = 'examen_aula';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'aula_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'aula_id' => 'integer',
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
