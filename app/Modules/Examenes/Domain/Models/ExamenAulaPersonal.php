<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Asignación de personal a un aula de examen ('a_cargo' o 'apoyo').
 *
 * @property int $id
 * @property int $examen_id
 * @property int $aula_id
 * @property int $usuario_id
 * @property string $rol
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ExamenAulaPersonal extends Model
{
    public const ROL_A_CARGO = 'a_cargo';
    public const ROL_APOYO = 'apoyo';

    protected $table = 'examen_aula_personal';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'aula_id',
        'usuario_id',
        'rol',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'aula_id' => 'integer',
            'usuario_id' => 'integer',
            'rol' => 'string',
        ];
    }

    /**
     * @return BelongsTo<Examen, $this>
     */
    public function examen(): BelongsTo
    {
        return $this->belongsTo(Examen::class, 'examen_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
