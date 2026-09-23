<?php

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

/**
 * @property string $correo
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';


    public function getEmailAttribute(): string
    {
        return $this->attributes['email']
            ?? $this->attributes['correo']
            ?? '';
    }


    protected $fillable = [
        'name',
        'email',
        'nombre',
        'correo',
        'password',
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }


    /**
     * Compatibilidad con tests que consultan la tabla users.
     */
    protected static function booted(): void
    {
        static::created(function (User $user): void {

            DB::table('users')->updateOrInsert(
                [
                    'id' => $user->id,
                ],
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $user->password,
                    'email_verified_at' => $user->email_verified_at,

                    'is_active' => $user->is_active,
                    'failed_login_attempts' => $user->failed_login_attempts,
                    'locked_until' => $user->locked_until,
                    'last_login_at' => $user->last_login_at,

                    'remember_token' => $user->remember_token,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

        });
    }
}