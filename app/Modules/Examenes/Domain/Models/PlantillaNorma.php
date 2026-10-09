<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Una norma reutilizable al registrar examenes. Sin cuenta es una norma
 * predefinida del sistema; con cuenta es la plantilla propia de esa persona
 * y nadie mas la ve.
 *
 * @property int $id
 * @property int|null $usuario_id
 * @property string $texto
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PlantillaNorma extends Model
{
    protected $table = 'plantillas_norma';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'texto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usuario_id' => 'integer',
        ];
    }

    public function esPredefinida(): bool
    {
        return $this->usuario_id === null;
    }
}
