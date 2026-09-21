<?php

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
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
}
