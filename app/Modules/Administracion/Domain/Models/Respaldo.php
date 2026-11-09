<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Models;

use App\Modules\Administracion\Domain\Enums\EstadoRespaldo;
use App\Modules\Administracion\Domain\Enums\TipoRespaldo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Una generacion de respaldo, correcta o fallida. Esta tabla queda fuera
 * del volcado: restaurar no borra el historial.
 *
 * @property int $id
 * @property string|null $archivo
 * @property TipoRespaldo $tipo
 * @property EstadoRespaldo $estado
 * @property int|null $tamano_bytes
 * @property int|null $tablas
 * @property int|null $filas
 * @property string|null $error
 * @property int|null $generado_por
 * @property Carbon|null $restaurado_en
 * @property int|null $restaurado_por
 * @property Carbon|null $created_at
 */
final class Respaldo extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'respaldos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'archivo',
        'tipo',
        'estado',
        'tamano_bytes',
        'tablas',
        'filas',
        'error',
        'generado_por',
        'restaurado_en',
        'restaurado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoRespaldo::class,
            'estado' => EstadoRespaldo::class,
            'tamano_bytes' => 'integer',
            'tablas' => 'integer',
            'filas' => 'integer',
            'generado_por' => 'integer',
            'restaurado_en' => 'datetime',
            'restaurado_por' => 'integer',
        ];
    }
}
