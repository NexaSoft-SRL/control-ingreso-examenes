<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un docente es una fila aunque dicte en varias facultades: se lo reconoce
 * por su nombre normalizado. Sus facultades se derivan de sus grupos.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $nombre_completo
 * @property string $nombre_normalizado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Docente extends Model
{
    protected $table = 'docentes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nombre_completo',
        'nombre_normalizado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<Grupo, $this>
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'docente_id');
    }

    public function tieneCuenta(): bool
    {
        return $this->user_id !== null;
    }
}
