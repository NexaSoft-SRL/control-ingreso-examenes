<?php

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nombre',
        'usuario',
        'correo',
        'password',
        'password_changed_at',
        'password_temporal_expira_en',
        'role_id',
        'is_active',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'password_temporal_expira_en' => 'datetime',
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Toda cuenta tiene un nombre de usuario unico. Quien la crea sin
     * indicarlo recibe uno derivado de la parte local del correo (en
     * minusculas, saneada y con sufijo 2, 3... si ya existe): la misma
     * regla con la que la migracion relleno las cuentas anteriores.
     */
    protected static function booted(): void
    {
        static::creating(static function (self $cuenta): void {
            $actual = $cuenta->getAttribute('usuario');

            if (is_string($actual) && $actual !== '') {
                return;
            }

            $correo = $cuenta->getAttribute('correo');
            $local = mb_strtolower(explode('@', is_string($correo) ? $correo : '', 2)[0]);
            $base = substr((string) preg_replace('/[^a-z0-9._-]+/', '', $local), 0, 50);

            if ($base === '') {
                $base = 'usuario';
            }

            $usuario = $base;

            for ($sufijo = 2; static::query()->where('usuario', $usuario)->exists(); $sufijo++) {
                $usuario = $base.$sufijo;
            }

            $cuenta->setAttribute('usuario', $usuario);
        });
    }

    public function getAuthIdentifierName()
    {
        return $this->getKeyName();
    }

    public function getEmailAttribute(): string
    {
        return $this->correo ?? '';
    }

    public function getNameAttribute(): string
    {
        return $this->nombre ?? '';
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['correo'] = $value;
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['nombre'] = $value;
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
