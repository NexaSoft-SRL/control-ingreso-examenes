<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Models;

use App\Modules\Academico\Domain\Enums\EstadoPeriodo;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un periodo academico (`2/2026`). Su estado no se guarda: se calcula.
 *
 * @property int $id
 * @property string $codigo
 * @property int $anio
 * @property int $numero
 * @property TipoPeriodo $tipo
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property array<mixed>|null $ventanas
 * @property string|null $fuente
 * @property int|null $ajustado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Periodo extends Model
{
    protected $table = 'periodos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'anio',
        'numero',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'ventanas',
        'fuente',
        'ajustado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'numero' => 'integer',
            'tipo' => TipoPeriodo::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'ventanas' => 'array',
            'ajustado_por' => 'integer',
        ];
    }

    /**
     * @return HasMany<Grupo, $this>
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'periodo_id');
    }

    public function estado(?Carbon $hoy = null): EstadoPeriodo
    {
        if ($this->fecha_inicio === null || $this->fecha_fin === null) {
            return EstadoPeriodo::SinFechas;
        }

        $dia = ($hoy ?? Carbon::today())->toDateString();

        if ($dia < $this->fecha_inicio->toDateString()) {
            return EstadoPeriodo::Proximo;
        }

        if ($dia > $this->fecha_fin->toDateString()) {
            return EstadoPeriodo::Cerrado;
        }

        return EstadoPeriodo::Vigente;
    }

    public function estaVigente(?Carbon $hoy = null): bool
    {
        return $this->estado($hoy) === EstadoPeriodo::Vigente;
    }
}
