<?php

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['name', 'screen_name'];

    /** @phpstan-ignore-next-line */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}