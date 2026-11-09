<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Models;

use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una incidencia de un examen: reportada por una persona o asentada por
 * la puerta. La expulsion y la situacion excepcional tambien lo son.
 *
 * @property int $id
 * @property int|null $examen_id
 * @property int|null $aula_id
 * @property int|null $estudiante_id
 * @property string|null $estudiante_texto
 * @property int|null $ingreso_id
 * @property TipoIncidencia $tipo
 * @property OrigenIncidencia $origen
 * @property CausaIntento|null $causa
 * @property int|null $examen_correspondiente_id
 * @property string $detalle
 * @property Carbon $ocurrida_en
 * @property int|null $reportada_por
 * @property int|null $acceso_aula_id
 * @property Carbon|null $atendida_en
 * @property int|null $atendida_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Incidencia extends Model
{
    protected $table = 'incidencias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'aula_id',
        'estudiante_id',
        'estudiante_texto',
        'ingreso_id',
        'tipo',
        'origen',
        'causa',
        'examen_correspondiente_id',
        'detalle',
        'ocurrida_en',
        'reportada_por',
        'acceso_aula_id',
        'atendida_en',
        'atendida_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'aula_id' => 'integer',
            'estudiante_id' => 'integer',
            'ingreso_id' => 'integer',
            'tipo' => TipoIncidencia::class,
            'origen' => OrigenIncidencia::class,
            'causa' => CausaIntento::class,
            'examen_correspondiente_id' => 'integer',
            'ocurrida_en' => 'datetime',
            'reportada_por' => 'integer',
            'acceso_aula_id' => 'integer',
            'atendida_en' => 'datetime',
            'atendida_por' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ingreso, $this>
     */
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class, 'ingreso_id');
    }

    public function estaAtendida(): bool
    {
        return $this->atendida_en !== null;
    }
}
