<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un aula de la oferta. Sin edificio es un aula que no se pudo ubicar. Solo
 * se conoce su nombre y donde queda: no lleva cupo ni estado.
 *
 * @property int $id
 * @property string $nombre
 * @property int|null $edificio_id
 * @property int|null $facultad_id
 * @property string|null $piso
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Aula extends Model
{
    protected $table = 'aulas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'edificio_id',
        'facultad_id',
        'piso',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edificio_id' => 'integer',
            'facultad_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Edificio, $this>
     */
    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class, 'edificio_id');
    }

    /**
     * @return BelongsTo<Facultad, $this>
     */
    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class, 'facultad_id');
    }
}
