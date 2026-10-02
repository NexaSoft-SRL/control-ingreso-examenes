<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Examen extends Model
{
    protected $table = 'examenes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_id',
        'nombre',
        'fecha',
        'hora_inicio',
        'duracion_minutos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'duracion_minutos' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GrupoAsignatura, $this>
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoAsignatura::class, 'grupo_id');
    }
}
