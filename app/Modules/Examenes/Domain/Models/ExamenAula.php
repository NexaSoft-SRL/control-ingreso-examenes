<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un aula del examen, con quien esta a cargo y, si se genero, el codigo de
 * acceso para el auxiliar ocasional.
 *
 * @property int $id
 * @property int $examen_id
 * @property int $aula_id
 * @property int $encargado_id
 * @property string|null $codigo_acceso
 * @property Carbon|null $codigo_generado_en
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
        'encargado_id',
        'codigo_acceso',
        'codigo_generado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'aula_id' => 'integer',
            'encargado_id' => 'integer',
            'codigo_generado_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Examen, $this>
     */
    public function examen(): BelongsTo
    {
        return $this->belongsTo(Examen::class, 'examen_id');
    }

    /**
     * @return HasMany<ExamenAulaApoyo, $this>
     */
    public function apoyos(): HasMany
    {
        return $this->hasMany(ExamenAulaApoyo::class, 'examen_aula_id');
    }

    /**
     * @return HasMany<AccesoAula, $this>
     */
    public function accesos(): HasMany
    {
        return $this->hasMany(AccesoAula::class, 'examen_aula_id');
    }
}
