<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sesión temporal abierta mediante código de aula (acceso sin cuenta).
 *
 * @property int $id
 * @property int $examen_id
 * @property int $aula_id
 * @property string $codigo
 * @property string $nombre_colaborador
 * @property string $token
 * @property Carbon $ultimo_acceso_en
 * @property Carbon|null $revocado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class AccesoCodigoAula extends Model
{
    protected $table = 'accesos_codigo_aula';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'aula_id',
        'codigo',
        'nombre_colaborador',
        'token',
        'ultimo_acceso_en',
        'revocado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'aula_id' => 'integer',
            'codigo' => 'string',
            'nombre_colaborador' => 'string',
            'token' => 'string',
            'ultimo_acceso_en' => 'datetime',
            'revocado_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Examen, $this>
     */
    public function examen(): BelongsTo
    {
        return $this->belongsTo(Examen::class, 'examen_id');
    }

    public function esValido(): bool
    {
        return $this->revocado_en === null;
    }
}
