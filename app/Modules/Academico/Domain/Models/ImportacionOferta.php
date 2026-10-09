<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use App\Modules\Academico\Domain\Enums\EstadoImportacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cada corrida del importador de una facultad, con su resumen o su error.
 *
 * @property int $id
 * @property int $facultad_id
 * @property EstadoImportacion $estado
 * @property string|null $periodo_codigo
 * @property Carbon|null $fecha_fuente
 * @property array<mixed>|null $resumen
 * @property string|null $error
 * @property int|null $ejecutada_por
 * @property Carbon $iniciada_en
 * @property Carbon|null $terminada_en
 */
final class ImportacionOferta extends Model
{
    public $timestamps = false;

    protected $table = 'importaciones_oferta';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'facultad_id',
        'estado',
        'periodo_codigo',
        'fecha_fuente',
        'resumen',
        'error',
        'ejecutada_por',
        'iniciada_en',
        'terminada_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facultad_id' => 'integer',
            'estado' => EstadoImportacion::class,
            'fecha_fuente' => 'date',
            'resumen' => 'array',
            'ejecutada_por' => 'integer',
            'iniciada_en' => 'datetime',
            'terminada_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Facultad, $this>
     */
    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class, 'facultad_id');
    }
}
