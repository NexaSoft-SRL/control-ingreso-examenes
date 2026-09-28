<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class Student extends Model
{
    protected $table = 'students';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo_universitario',
        'carrera',
        'nombre',
        'apellido',
        'ci',
        'correo',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
