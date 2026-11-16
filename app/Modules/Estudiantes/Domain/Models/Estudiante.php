<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Models;

use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un estudiante del padron. Se crea con una carga de inscritos; no se edita
 * a mano.
 *
 * @property int $id
 * @property string $codigo_universitario
 * @property string|null $documento_identidad
 * @property string $nombres
 * @property string $apellidos
 * @property int|null $carrera_id
 * @property int|null $facultad_id
 * @property OrigenEstudiante $origen
 * @property bool $verificado
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Estudiante extends Model
{
    /**
     * El correo no se guarda: es el codigo universitario en el dominio de
     * estudiantes de la universidad.
     */
    public const DOMINIO_CORREO = 'est.umss.edu';

    protected $table = 'estudiantes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo_universitario',
        'documento_identidad',
        'nombres',
        'apellidos',
        'carrera_id',
        'facultad_id',
        'origen',
        'verificado',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'carrera_id' => 'integer',
            'facultad_id' => 'integer',
            'origen' => OrigenEstudiante::class,
            'verificado' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Inscripcion, $this>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'estudiante_id');
    }

    public function correo(): string
    {
        return self::correoDe($this->codigo_universitario);
    }

    /**
     * El correo de un codigo universitario, este o no en el padron.
     */
    public static function correoDe(string $codigoUniversitario): string
    {
        $dominio = config('umss.dominio_correo_estudiantes');

        return $codigoUniversitario.'@'.(is_string($dominio) && $dominio !== '' ? $dominio : self::DOMINIO_CORREO);
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->apellidos} {$this->nombres}");
    }
}
