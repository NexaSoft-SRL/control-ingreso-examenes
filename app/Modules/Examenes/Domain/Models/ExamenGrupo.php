<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un grupo que rinde el examen.
 *
 * @property int $id
 * @property int $examen_id
 * @property int $grupo_id
 */
final class ExamenGrupo extends Model
{
    public $timestamps = false;

    protected $table = 'examen_grupo';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'grupo_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'grupo_id' => 'integer',
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
