<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class NormaExamen extends Model
{
    protected $table = 'normas_examenes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'alcance',
        'texto',
        'estudiante_id',
        'motivo',
    ];
}
