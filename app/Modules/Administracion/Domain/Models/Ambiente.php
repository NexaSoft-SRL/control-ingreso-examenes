<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class Ambiente extends Model
{
    protected $table = 'ambientes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'ubicacion',
        'capacidad',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacidad' => 'integer',
        ];
    }
}
