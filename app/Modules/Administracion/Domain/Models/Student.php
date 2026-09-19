<?php

namespace App\Modules\Administracion\Domain\Models;

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

    public function scopeActivos($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeBuscar($query, string $termino)
    {
        return $query->where(function ($q) use ($termino) {
            $q->where('codigo_sis', 'ILIKE', '%' . $termino . '%')
                ->orWhere('ci', 'ILIKE', '%' . $termino . '%')
                ->orWhere('nombres', 'ILIKE', '%' . $termino . '%')
                ->orWhere('apellidos', 'ILIKE', '%' . $termino . '%');
        });
    }
}
