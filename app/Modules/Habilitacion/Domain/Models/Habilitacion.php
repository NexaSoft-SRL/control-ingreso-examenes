<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * La condicion de un inscrito frente a un examen y el aula que le toca.
 * «Sin revisar» es la ausencia de fila.
 *
 * @property int $id
 * @property int $examen_id
 * @property int $estudiante_id
 * @property bool $habilitado
 * @property int|null $aula_id
 * @property string|null $motivo
 * @property int|null $registrada_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Habilitacion extends Model
{
    protected $table = 'habilitaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'estudiante_id',
        'habilitado',
        'aula_id',
        'motivo',
        'registrada_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'estudiante_id' => 'integer',
            'habilitado' => 'boolean',
            'aula_id' => 'integer',
            'registrada_por' => 'integer',
        ];
    }
}
