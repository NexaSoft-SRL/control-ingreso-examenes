<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Una suspensión temporal registrada por el docente.
 *
 * @property int $id
 * @property int $examen_id
 * @property int $user_id
 * @property string $motivo
 * @property string|null $descripcion
 * @property Carbon $suspendido_en
 * @property Carbon|null $reanudado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class SuspensionExamen extends Model
{
    protected $table = 'suspensiones_examen';

    protected $fillable = [
        'examen_id',
        'user_id',
        'motivo',
        'descripcion',
        'suspendido_en',
        'reanudado_en',
    ];

    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'user_id' => 'integer',
            'suspendido_en' => 'datetime',
            'reanudado_en' => 'datetime',
        ];
    }
}
