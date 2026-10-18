<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Código de acceso de 6 dígitos propio de un aula y examen.
 *
 * @property int $id
 * @property int $examen_id
 * @property int $aula_id
 * @property string $codigo
 * @property Carbon $generado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ExamenAulaCodigo extends Model
{
    protected $table = 'examen_aula_codigos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'aula_id',
        'codigo',
        'generado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'aula_id' => 'integer',
            'codigo' => 'string',
            'generado_en' => 'datetime',
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
