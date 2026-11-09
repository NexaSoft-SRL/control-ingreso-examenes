<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * El codigo QR de un estudiante para un examen. El token es opaco.
 *
 * @property int $id
 * @property int $examen_id
 * @property int $estudiante_id
 * @property string $token
 * @property Carbon $emitido_en
 * @property int|null $emitido_por
 * @property Carbon|null $enviado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class CodigoQr extends Model
{
    protected $table = 'codigos_qr';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'examen_id',
        'estudiante_id',
        'token',
        'emitido_en',
        'emitido_por',
        'enviado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examen_id' => 'integer',
            'estudiante_id' => 'integer',
            'emitido_en' => 'datetime',
            'emitido_por' => 'integer',
            'enviado_en' => 'datetime',
        ];
    }
}
