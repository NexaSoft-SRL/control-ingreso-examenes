<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Models;

use App\Modules\Ingreso\Domain\Enums\MedioIdentificacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * El ingreso de un estudiante a un examen. Es unico e inmutable: no se
 * modifica ni se borra, y en PostgreSQL lo impide un disparador.
 *
 * @property int $id
 * @property int $examen_id
 * @property int $estudiante_id
 * @property int $aula_id
 * @property MedioIdentificacion $medio
 * @property bool $es_excepcion
 * @property Carbon $registrado_en
 * @property int|null $registrado_por
 * @property int|null $acceso_aula_id
 * @property Carbon|null $created_at
 */
final class Ingreso extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ingresos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'estudiante_id',
        'aula_id',
        'medio',
        'es_excepcion',
        'registrado_en',
        'registrado_por',
        'acceso_aula_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'estudiante_id' => 'integer',
            'aula_id' => 'integer',
            'medio' => MedioIdentificacion::class,
            'es_excepcion' => 'boolean',
            'registrado_en' => 'datetime',
            'registrado_por' => 'integer',
            'acceso_aula_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<Incidencia, $this>
     */
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'ingreso_id');
    }
}
