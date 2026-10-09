<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Models;

use App\Modules\Estudiantes\Domain\Enums\EstadoConflicto;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Enums\ResolucionConflicto;
use App\Modules\Estudiantes\Domain\Enums\TipoConflicto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una fila de carga que no coincide con el estudiante guardado. La
 * inscripcion queda en espera hasta que se resuelva.
 *
 * @property int $id
 * @property int $estudiante_id
 * @property int|null $grupo_id
 * @property int|null $carga_id
 * @property int|null $fila
 * @property TipoConflicto $tipo
 * @property string|null $documento_nuevo
 * @property string $nombres_nuevos
 * @property string $apellidos_nuevos
 * @property OrigenEstudiante $via
 * @property EstadoConflicto $estado
 * @property ResolucionConflicto|null $resolucion
 * @property int $reportado_por
 * @property int|null $resuelto_por
 * @property Carbon|null $resuelto_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ConflictoPadron extends Model
{
    protected $table = 'conflictos_padron';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'estudiante_id',
        'grupo_id',
        'carga_id',
        'fila',
        'tipo',
        'documento_nuevo',
        'nombres_nuevos',
        'apellidos_nuevos',
        'via',
        'estado',
        'resolucion',
        'reportado_por',
        'resuelto_por',
        'resuelto_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estudiante_id' => 'integer',
            'grupo_id' => 'integer',
            'carga_id' => 'integer',
            'fila' => 'integer',
            'tipo' => TipoConflicto::class,
            'via' => OrigenEstudiante::class,
            'estado' => EstadoConflicto::class,
            'resolucion' => ResolucionConflicto::class,
            'reportado_por' => 'integer',
            'resuelto_por' => 'integer',
            'resuelto_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Estudiante, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    /**
     * @return BelongsTo<CargaInscritos, $this>
     */
    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaInscritos::class, 'carga_id');
    }
}
