<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Auxiliar vinculado a la lista de un docente.
 *
 * @property int $id
 * @property int $docente_id
 * @property int $auxiliar_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class DocenteAuxiliar extends Model
{
    protected $table = 'docente_auxiliares';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'docente_id',
        'auxiliar_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'docente_id' => 'integer',
            'auxiliar_id' => 'integer',
        ];
    }
}
