<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una carrera de la oferta, identificada por su codigo de la universidad.
 *
 * @property int $id
 * @property int $facultad_id
 * @property string $codigo
 * @property string $nombre
 * @property RegimenCarrera $regimen
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Carrera extends Model
{
    protected $table = 'carreras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'facultad_id',
        'codigo',
        'nombre',
        'regimen',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facultad_id' => 'integer',
            'regimen' => RegimenCarrera::class,
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
     * @return HasMany<PlanEstudio, $this>
     */
    public function planes(): HasMany
    {
        return $this->hasMany(PlanEstudio::class, 'carrera_id');
    }
}
