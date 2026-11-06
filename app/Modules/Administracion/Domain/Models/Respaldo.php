<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HU-25: cada intento de respaldo del sistema (programado o puntual),
 * con su archivo en disco, tamano y estado resultante.
 */
class Respaldo extends Model
{
    /**
     * @var string
     */
    protected $table = 'respaldos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'archivo',
        'tipo',
        'tamano_bytes',
        'estado',
        'error',
        'version_esquema',
        'usuario_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
