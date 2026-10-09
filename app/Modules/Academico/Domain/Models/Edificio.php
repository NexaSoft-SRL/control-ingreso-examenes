<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un edificio del campus con su poligono, para dibujarlo en el mapa.
 *
 * @property int $id
 * @property int $facultad_id
 * @property string $clave
 * @property string $nombre
 * @property array<mixed> $poligono
 * @property float $centro_lon
 * @property float $centro_lat
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Edificio extends Model
{
    protected $table = 'edificios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'facultad_id',
        'clave',
        'nombre',
        'poligono',
        'centro_lon',
        'centro_lat',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facultad_id' => 'integer',
            'poligono' => 'array',
            'centro_lon' => 'float',
            'centro_lat' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Facultad, $this>
     */
    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class, 'facultad_id');
    }

    /**
     * @return HasMany<Aula, $this>
     */
    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class, 'edificio_id');
    }
}
