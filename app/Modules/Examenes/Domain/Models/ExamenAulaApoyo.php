<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quien apoya al encargado en un aula del examen.
 *
 * @property int $id
 * @property int $examen_aula_id
 * @property int $user_id
 */
final class ExamenAulaApoyo extends Model
{
    public $timestamps = false;

    protected $table = 'examen_aula_apoyo';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_aula_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_aula_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExamenAula, $this>
     */
    public function examenAula(): BelongsTo
    {
        return $this->belongsTo(ExamenAula::class, 'examen_aula_id');
    }
}
