<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auxiliar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auxiliar_id');
    }
}
