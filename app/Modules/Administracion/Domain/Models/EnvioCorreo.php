<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Models;

use App\Modules\Administracion\Domain\Enums\EstadoEnvio;
use App\Modules\Administracion\Domain\Enums\TipoCorreo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un correo que envio el sistema y como termino.
 *
 * @property int $id
 * @property TipoCorreo $tipo
 * @property string $destinatario
 * @property int|null $examen_id
 * @property int|null $estudiante_id
 * @property EstadoEnvio $estado
 * @property string|null $error
 * @property int|null $solicitado_por
 * @property Carbon|null $enviado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EnvioCorreo extends Model
{
    protected $table = 'envios_correo';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tipo',
        'destinatario',
        'examen_id',
        'estudiante_id',
        'estado',
        'error',
        'solicitado_por',
        'enviado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoCorreo::class,
            'examen_id' => 'integer',
            'estudiante_id' => 'integer',
            'estado' => EstadoEnvio::class,
            'solicitado_por' => 'integer',
            'enviado_en' => 'datetime',
        ];
    }
}
