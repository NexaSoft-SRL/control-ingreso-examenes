<?php

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    protected $table = 'estudiantes';

    protected $fillable = [
        'carrera_id',
        'codigo_sis',
        'ci',
        'nombres',
        'apellidos',
        'correo',
        'telefono',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'carrera_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeBuscar(Builder $query, string $termino): Builder
    {
        return $query->where(function (Builder $q) use ($termino) {
            $q->where('codigo_sis', 'ILIKE', '%' . $termino . '%')
                ->orWhere('ci', 'ILIKE', '%' . $termino . '%')
                ->orWhere('nombres', 'ILIKE', '%' . $termino . '%')
                ->orWhere('apellidos', 'ILIKE', '%' . $termino . '%');
        });
    }
}
