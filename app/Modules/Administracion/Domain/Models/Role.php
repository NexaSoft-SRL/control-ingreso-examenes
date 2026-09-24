<?php

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name'];

    /** @phpstan-ignore-next-line */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    /** @phpstan-ignore-next-line */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}