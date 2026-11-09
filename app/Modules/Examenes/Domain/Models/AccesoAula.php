<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una sesion abierta con el codigo de un aula: quien entro y desde donde.
 *
 * @property int $id
 * @property int $examen_aula_id
 * @property string $nombre
 * @property string|null $ip
 * @property Carbon $creado_en
 */
final class AccesoAula extends Model
{
    public $timestamps = false;

    protected $table = 'accesos_aula';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_aula_id',
        'nombre',
        'ip',
        'creado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_aula_id' => 'integer',
            'creado_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExamenAula, $this>
     */
    public function examenAula(): BelongsTo
    {
        return $this->belongsTo(ExamenAula::class, 'examen_aula_id');
    }
}
