<?php

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\StudentFactory;

class EloquentStudent extends Model
{
    use HasFactory;

    protected $table = 'students';

    protected $fillable = [
        'nombre',
        'apellido',
        'ci',
        'correo',
        'activo',
    ];

    protected static function newFactory()
    {
        return StudentFactory::new();
    }
}
