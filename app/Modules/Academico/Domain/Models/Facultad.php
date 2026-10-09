<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalogo fijo de cuatro facultades. La clave es el nombre de su archivo
 * de oferta; la sigla es lo que se muestra.
 *
 * @property int $id
 * @property string $clave
 * @property string $sigla
 * @property string $nombre
 * @property string $codigo_umss
 * @property string $color
 * @property int $orden
 */
final class Facultad extends Model
{
    public $timestamps = false;

    protected $table = 'facultades';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clave',
        'sigla',
        'nombre',
        'codigo_umss',
        'color',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    /**
     * @return HasMany<Carrera, $this>
     */
    public function carreras(): HasMany
    {
        return $this->hasMany(Carrera::class, 'facultad_id');
    }

    /**
     * @return HasMany<Edificio, $this>
     */
    public function edificios(): HasMany
    {
        return $this->hasMany(Edificio::class, 'facultad_id');
    }
}
