<?php

namespace App\Modules\Estudiantes\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $fillable = [
        'codigo_sis',
        'ci',
        'nombres',
        'apellidos',
        'correo'
    ];
}