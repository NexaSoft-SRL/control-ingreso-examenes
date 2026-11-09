<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * El auxiliar es una cuenta con rol Auxiliar. Aqui solo se guarda quien lo
 * dio de alta y su codigo universitario.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $codigo_universitario
 * @property int|null $registrado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Auxiliar extends Model
{
    protected $table = 'auxiliares';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'codigo_universitario',
        'registrado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'registrado_por' => 'integer',
        ];
    }
}
