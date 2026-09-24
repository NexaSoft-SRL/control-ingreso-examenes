<?php

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'correo',
        'password',
        'rol_id',
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
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }


    public function getAuthIdentifierName()
    {
        return 'correo';
    }


    // AGREGA ESTO

    public function getEmailAttribute()
    {
        return $this->correo;
    }


    public function getNameAttribute()
    {
        return $this->nombre;
    }


    public function setEmailAttribute($value)
    {
        $this->attributes['correo'] = $value;
    }


    public function setNameAttribute($value)
    {
        $this->attributes['nombre'] = $value;
    }
}